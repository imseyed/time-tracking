# Time Tracking Panel (Clockify-style)

سامانه ثبت ساعت کاری با PHP/MySQL + Svelte و رابط کاربری پنلی (منوی سمت راست) با تم نارنجی `#FC572C`.

## مشخصات ورود پیش‌فرض
- Admin: `admin / public`

## امکانات
- ثبت ساعت با تاریخ شمسی (ورودی کاربر)
- نمایش 25 رکورد اخیر کاربر در صفحه ثبت ساعت
- گزارش‌گیری پیش‌فرض 30 روز اخیر
- نمودار میله‌ای **عمودی** ساعات روزانه در بازه انتخاب‌شده
- مدیریت کاربران (لیست + ایجاد / ویرایش / حذف)
- مدیریت پروژه‌ها (ایجاد / ویرایش / حذف)
- منوها و دکمه‌ها همراه آیکن

## فونت‌های IRANSansX
فایل‌ها را در این مسیر قرار دهید:
- `frontend/public/fonts/IRANSansXFaNum-Medium.woff2`
- `frontend/public/fonts/IRANSansXFaNum-ExtraBold.woff`

## اجرا
### Backend
```bash
cd backend
php -S localhost:8080
```

### Frontend
```bash
cd frontend
npm install
npm run dev
```

## نکته دیتابیس
قبل از اجرا، `backend/init.sql` را روی دیتابیس MySQL خود اجرا کنید.
