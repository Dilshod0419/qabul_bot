<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
define('TOKEN', '8778118740:AAEyp08CXmKfHtTJ-pUOJEzBu74IMGfTaaA'); 
define('OPERATOR_CHANNEL', '-1004358285483'); 
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo "Bot tizimi faol ishlayapti!";
    exit;
}

$message = $update['message'] ?? null;
$callback_query = $update['callback_query'] ?? null;

// --- A) AGAR FOYDALANUVCHIDAN ODDIY XABAR YOKI KONTAKT KELSA ---
if ($message) {
    $chat_id = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $contact = $message['contact'] ?? null;
    $username = $message['from']['username'] ? '@' . $message['from']['username'] : 'Mavjud emas';

    // Foydalanuvchini bazadan qidiramiz
    $stmt = $pdo->prepare("SELECT * FROM users WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $user = $stmt->fetch();

    // /start buyrug'i kelgandagi mantiq
    if ($text == '/start') {

        // 1) Agar foydalanuvchi avval ro'yxatdan to'liq o'tgan bo'lsa (ism+telefon mavjud)
        //    -> qayta ism/telefon so'ramaymiz, to'g'ridan-to'g'ri savol so'raymiz
        if ($user && !empty($user['full_name']) && !empty($user['phone'])) {
            updateStep($chat_id, 'question_again', $pdo);
            sendMessage($chat_id, "Assalomu alaykum <b>" . htmlspecialchars($user['full_name']) . "</b>! \n\nQabul bo'yicha qanday savolingiz bor? Murojaatingizni yozib qoldiring, operatorlarimiz siz bilan bog'lanishadi.");
            exit;
        }

        // 2) Foydalanuvchi yangi bo'lsa yoki ism/telefon bosqichida to'xtab qolgan bo'lsa -> boshidan boshlaymiz
        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (chat_id, step) VALUES (?, 'name')");
            $stmt->execute([$chat_id]);
        } else {
            updateStep($chat_id, 'name', $pdo);
        }

        // Chiroyli kutib olish matni va Ism so'rash
        $welcome_text = "✨ <b>Xush kelibsiz!</b>\n\nKamoliddin Behzod nomidagi Milliy rassomlik va dizayn institutining <b>2026-2027 o'quv yili</b> qabul murojaat botiga xush kelibsiz.\n\n" .
                       "Sizga sifatli xizmat ko'rsatishimiz uchun iltimos, avval <b>Ism va Familiyangizni</b> kiriting:";
        
        sendMessage($chat_id, $welcome_text, json_encode(['remove_keyboard' => true]));
        exit;
    }

    // Foydalanuvchi joriy bosqichini tekshirish (Agar bazada yo'q bo'lsa, e'tiborsiz qoldiramiz)
    if (!$user) exit;

    switch ($user['step']) {
        case 'name':
            if (!empty($text) && strlen($text) > 3) {
                // Ismni saqlab, telefon so'rash bosqichiga o'tkazamiz
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, step = 'phone' WHERE chat_id = ?");
                $stmt->execute([$text, $chat_id]);

                $reply_markup = json_encode([
                    'keyboard' => [
                        [['text' => "📱 Telefon raqamni yuborish", 'request_contact' => true]]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ]);

                sendMessage($chat_id, "Rahmat! Endi pastdagi tugmani bosish orqali <b>ishlaydigan telefon raqamingizni</b> yuboring:", $reply_markup);
            } else {
                sendMessage($chat_id, "❌ Iltimos, ism va familiyangizni to'g'ri shaklda kiriting.");
            }
            break;

        case 'phone':
            if ($contact) {
                $phone = $contact['phone_number'];
                
                $stmt = $pdo->prepare("UPDATE users SET phone = ?, step = 'question' WHERE chat_id = ?");
                $stmt->execute([$phone, $chat_id]);

                sendMessage($chat_id, "Ajoyib! Endi qabul bo'yicha o'zingizni qiziqtirgan qanday <b>murojaat yoki savolingiz</b> bo'lsa, pastdan matn ko'rinishida yozib yuboring:", json_encode(['remove_keyboard' => true]));
            } else {
                sendMessage($chat_id, "⚠️ Iltimos, pastdagi <b>'📱 Telefon raqamni yuborish'</b> tugmasini bosing. Bu operatorlarimiz siz bilan bog'lanishi uchun zarur.");
            }
            break;

        case 'question':
        case 'question_again':
            if (!empty($text) && strlen($text) > 5) {
                $current_step = $user['step'];

                // Savolni bazaga yozib qo'yamiz va holatni yakunlaymiz
                $stmt = $pdo->prepare("UPDATE users SET question = ?, step = 'done' WHERE chat_id = ?");
                $stmt->execute([$text, $chat_id]);

                // Agar foydalanuvchi qayta start bosib savol bergan bo'lsa, uning eski saqlangan ma'lumotlarini chaqiramiz
                $student_name = $user['full_name'];
                $student_phone = $user['phone'];
                $report_type = ($current_step == 'question_again') ? "🔄 QAYTA MUROJAAT" : "📥 YANGI MUROJAAT";
                
                $channel_message = "<b>" . $report_type . "</b>\n";
                $channel_message .= "───────────────────\n";
                $channel_message .= "👤 <b>Foydalanuvchi:</b> " . htmlspecialchars($student_name) . "\n";
                $channel_message .= "📞 <b>Telefon:</b> " . htmlspecialchars($student_phone) . "\n";
                $channel_message .= "🔗 <b>Telegram:</b> " . $username . "\n";
                $channel_message .= "🆔 <b>Chat ID:</b> <code>" . $chat_id . "</code>\n";
                $channel_message .= "───────────────────\n";
                $channel_message .= "❓ <b>Savol matni:</b>\n" . htmlspecialchars($text) . "\n";
                $channel_message .= "───────────────────\n";

                // Operatorlar kanaliga jo'natish
                sendMessage(OPERATOR_CHANNEL, $channel_message);

                // Talabaning o'ziga tasdiq xabari
                sendMessage($chat_id, "✅ Rahmat! Sizning murojaatingiz operatorlar kanaliga yo'llandi. Tez orada mutaxassislarimiz siz ko'rsatgan <b>+" . htmlspecialchars($student_phone) . "</b> raqami orqali aloqaga chiqishadi.");
            } else {
                sendMessage($chat_id, "❌ Iltimos, qabul bo'yicha savolingizni batafsilroq yozib qoldiring.");
            }
            break;
            
        case 'done':
            sendMessage($chat_id, "Sizning so'rovingiz allaqachon operatorlarga yuborilgan. Agar yangi murojaat yo'llamoqchi bo'lsangiz, /start buyrug'ini bosing.");
            break;
    }
}


function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    callTelegram('sendMessage', $data);
}

function updateStep($chat_id, $step, $pdo) {
    $stmt = $pdo->prepare("UPDATE users SET step = ? WHERE chat_id = ?");
    $stmt->execute([$step, $chat_id]);
}

function callTelegram($method, $data) {
    $url = "https://api.telegram.org/bot" . TOKEN . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}