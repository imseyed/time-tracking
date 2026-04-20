# Time Tracking Panel (Clockify-style)

سامانه ثبت ساعت کاری با معماری:

- **Backend:** PHP + MySQL
- **Frontend:** Svelte (Vite)
- **Theme:** نارنجی بر پایه `#FC572C`
- **Layout:** پنل با منوی سمت راست

## قابلیت‌ها

### دسترسی ادمین
- ورود با اکانت پیش‌فرض: `admin / public`
- ایجاد کاربر
- ویرایش کاربر
- ایجاد پروژه
- گزارش‌گیری از همه کاربران
- ثبت ساعت برای خود یا کاربر انتخاب‌شده

### دسترسی کاربر عادی
- ثبت ساعت برای خودش
- گزارش‌گیری فقط از اطلاعات خودش

## فونت‌های IRANSansX
برای اعمال فونت‌ها، فایل‌های زیر را در این مسیر کپی کنید:

- `frontend/public/fonts/IRANSansXFaNum-Medium.woff2`
- `frontend/public/fonts/IRANSansXFaNum-ExtraBold.woff`

پس از قرار دادن فونت‌ها، UI به‌صورت خودکار از آن‌ها استفاده می‌کند.

## ساختار پروژه

- `backend/`
  - `init.sql`: ساخت جداول + داده اولیه
  - `config.php`: تنظیم اتصال DB
  - `api.php`: API احراز هویت، کاربران، پروژه‌ها، ثبت ساعت، گزارش
- `frontend/`
  - `src/App.svelte`: پنل اصلی + منو + صفحات
  - `public/fonts/`: محل فایل فونت

## راه‌اندازی Backend

1. دیتابیس MySQL بسازید (مثلاً `timetracking`).
2. فایل `backend/init.sql` را اجرا کنید.
3. در `backend/config.php` اطلاعات اتصال را تنظیم کنید.
4. اجرا:

```bash
cd backend
php -S localhost:8080
```

## راه‌اندازی Frontend

```bash
cd frontend
npm install
npm run dev
```

فرانت به API این آدرس وصل می‌شود:

`http://localhost:8080/api.php`
