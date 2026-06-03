# SMS Gateway Cloud Chat

## Deploy บน Render.com

1. Push โฟลเดอร์นี้ขึ้น GitHub repo
2. ไปที่ [render.com](https://render.com) → New → Web Service
3. เชื่อม GitHub repo → เลือก **Docker** runtime
4. กด **Deploy**
5. เปิด URL ที่ Render ให้มา
6. กดปุ่ม **ลงทะเบียน Webhook** ในหน้าเว็บ

## โครงสร้างไฟล์
```
index.php       — แอปหลัก (ส่ง/รับ SMS + webhook receiver)
Dockerfile      — PHP 8.2 + Apache
render.yaml     — Render config
composer.json   — PHP metadata
```

## Credentials (อยู่ใน index.php แล้ว)
- Username: JTFBNP
- Device ID: U-ucDm6OQfO6FlCytxNIE
- API: https://api.sms-gate.app/3rdparty/v1
