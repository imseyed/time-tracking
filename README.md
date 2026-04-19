# Time Tracking (Clockify-style)

این پروژه یک سامانه ثبت ساعت کاری شبیه Clockify است با:

- **Backend:** PHP + MySQL
- **Frontend:** Svelte (Vite)
- **Theme:** نارنجی بر پایه `#FC572C`

## امکانات

1. **ثبت ساعت کاری**
   - تاریخ
   - زمان شروع
   - زمان پایان
   - انتخاب پروژه
   - شرح کار انجام شده
2. **گزارش‌گیری**
   - فیلتر بر اساس فرد
   - بازه زمانی شروع/پایان
   - پروژه
   - نمایش جزئیات رکوردها
   - نمودار مجموع ساعات به تفکیک پروژه

---

## ساختار پروژه

- `backend/`
  - `init.sql`: ساخت جداول و داده اولیه
  - `config.php`: تنظیمات اتصال DB
  - `api.php`: API ساده برای پروژه/افراد/ثبت ساعت/گزارش
- `frontend/`
  - برنامه Svelte با دو صفحه:
    - ثبت ساعت
    - گزارش‌گیری

---

## راه‌اندازی Backend (PHP/MySQL)

1. یک دیتابیس MySQL بسازید، مثلا `timetracking`.
2. فایل `backend/init.sql` را روی دیتابیس اجرا کنید.
3. در `backend/config.php` اطلاعات اتصال را تنظیم کنید.
4. با PHP داخلی اجرا کنید:

```bash
cd backend
php -S localhost:8080
```

API روی `http://localhost:8080/api.php` در دسترس خواهد بود.

---

## راه‌اندازی Frontend (Svelte)

```bash
cd frontend
npm install
npm run dev
```

اپ فرانت روی پورت پیش‌فرض Vite اجرا می‌شود و به `http://localhost:8080/api.php` درخواست می‌زند.

در صورت نیاز آدرس API را در `frontend/src/App.svelte` تغییر دهید.
