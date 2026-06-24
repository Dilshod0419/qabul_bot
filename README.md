# 🤖 MRDI Qabul Murojaat Boti

Kamoliddin Behzod nomidagi Milliy rassomlik va dizayn instituti uchun Telegram murojaat boti.

---

## 📋 Loyiha tuzilmasi

```
telegram-bot/
├── index.php          # Bot asosiy kodi (webhook handler)
├── db.php             # Ma'lumotlar bazasiga ulanish
├── init.sql           # Baza va jadval yaratish skripti
├── Dockerfile         # PHP container
├── docker-compose.yml # Barcha servislar (PHP + Nginx + MariaDB)
├── nginx.conf         # Nginx sozlamalari
├── .env.example       # Environment o'zgaruvchilar namunasi
└── README.md          # Hujjat
```

---

## ⚙️ Talablar

- Docker & Docker Compose o'rnatilgan server
- Tashqi IP yoki domen (Telegram HTTPS talab qiladi)
- SSL sertifikat (webhook uchun majburiy)

---

## 🚀 O'rnatish va ishga tushirish

### 1. Loyihani serverga ko'chirish

```bash
git clone <repo_url> /var/www/telegram-bot
cd /var/www/telegram-bot
```

Yoki fayllarni to'g'ridan-to'g'ri yuklab:

```bash
mkdir -p /var/www/telegram-bot
# Fayllarni papkaga ko'chiring
```

### 2. Environment faylini sozlash

```bash
cp .env.example .env
nano .env
```

`.env` faylida quyidagilarni o'zgartiring:

```env
BOT_TOKEN=your_bot_token_here
OPERATOR_CHANNEL=your_channel_id_here
DB_PASSWORD=your_strong_password
```

### 3. Docker bilan ishga tushirish

```bash
docker-compose up -d
```

Barcha konteynerlar ishga tushganini tekshirish:

```bash
docker-compose ps
```

### 4. Webhook o'rnatish

Bot ishlashi uchun Telegram serveriga webhook URL ni ro'yxatdan o'tkazish kerak:

```bash
curl "https://api.telegram.org/bot<BOT_TOKEN>/setWebhook?url=https://your-domain.uz/telegram-bot/index.php"
```

Webhook holatini tekshirish:

```bash
curl "https://api.telegram.org/bot<BOT_TOKEN>/getWebhookInfo"
```

---

## 🗄️ Ma'lumotlar bazasi

Baza avtomatik yaratiladi (`init.sql` orqali). Qo'lda yaratish kerak bo'lsa:

```bash
docker-compose exec db mysql -u root -p institute_db < init.sql
```

### `users` jadvali tuzilmasi

| Ustun | Tur | Tavsif |
|-------|-----|--------|
| id | INT | Avtomatik ID |
| chat_id | BIGINT | Telegram foydalanuvchi ID |
| full_name | VARCHAR | Ism va familiya |
| phone | VARCHAR | Telefon raqam |
| question | TEXT | Murojaat matni |
| step | VARCHAR | Joriy bosqich |
| created_at | TIMESTAMP | Ro'yxatga olingan vaqt |

---

## 🤖 Bot ishlash tartibi

```
/start
  ├── Yangi foydalanuvchi → Ism so'raladi
  │     └── Ism kiritildi → Telefon so'raladi
  │           └── Telefon yuborildi → Savol so'raladi
  │                 └── Savol yozildi → Operatorlar kanaliga yuboriladi ✅
  │
  └── Qaytgan foydalanuvchi (ism+tel mavjud) → To'g'ridan savol so'raladi
        └── Savol yozildi → Operatorlar kanaliga yuboriladi ✅
```

---

## 🔧 Foydali buyruqlar

```bash
# Konteynerlarni to'xtatish
docker-compose down

# Loglarni ko'rish
docker-compose logs -f php

# Nginx loglarini ko'rish
docker-compose logs -f nginx

# Bazaga kirish
docker-compose exec db mysql -u root -p institute_db

# PHP konteyneriga kirish
docker-compose exec php sh

# Konteynerlarni qayta ishga tushirish
docker-compose restart
```

---

## 🌐 Nginx (tashqi server) sozlamalari

Agar loyiha tashqi nginx orqali ishlasa (masalan, reverse proxy), quyidagini qo'shing:

```nginx
location ^~ /telegram-bot {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

---

## 🔒 Xavfsizlik

- `.env` faylini hech qachon git ga push qilmang
- `DB_PASSWORD` ni murakkab qiling
- Bot token ni oshkor qilmang
- Production da `display_errors` ni o'chiring (`index.php` da)

---

## 📞 Muammo bo'lsa

1. Webhook xatosini tekshiring: `getWebhookInfo`
2. PHP loglarini ko'ring: `docker-compose logs php`
3. Baza ulanishini tekshiring: `docker-compose exec db mysql -u root -p`
