
# Project Setup Guide

Follow the steps below to set up and run the project locally.

---

## Prerequisites

Make sure the following are installed on your system:

* PHP (with Composer)
* Node.js & npm
* Python (with pip)
* XAMPP (Apache & MySQL)
* Git

---

## 🔐 Required Files & Directories

1. Place the **Firebase credentials file** into:

   ```
   storage/app/firebase/
   ```
2. Place the following **Certificate Authority (CA)** files into:

   ```
   storage/app/private/ca/
   ```

   * `ca.csr`
   * `ca.key`
   * `ca.crt`

   > ℹ️ Create the directory if it does not exist.
   >

## 🔐 OpenSSL Configuration (Required – Windows / XAMPP)

> ⚠️ **Important:** This project requires OpenSSL to be **enabled** for cryptographic operations such as certificate generation, document signing, and encryption.

### Steps

1. Add the following OpenSSL configuration file path to your  **System Environment Variables** :

<pre class="overflow-visible! px-0!" data-start="732" data-end="779"><div class="contain-inline-size rounded-2xl corner-superellipse/1.1 relative bg-token-sidebar-surface-primary"><div class="sticky top-[calc(--spacing(9)+var(--header-height))] @w-xl/main:top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre!"><span><span>C:\xampp\php\extras\openssl\openssl.cnf</span><span>
</span></span></code></div></div></pre>

2. Open the PHP configuration file:

<pre class="overflow-visible! px-0!" data-start="818" data-end="846"><div class="contain-inline-size rounded-2xl corner-superellipse/1.1 relative bg-token-sidebar-surface-primary"><div class="sticky top-[calc(--spacing(9)+var(--header-height))] @w-xl/main:top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre!"><span><span>C:\xampp\php\php.ini</span><span>
</span></span></code></div></div></pre>

3. **Enable the OpenSSL extension** by making sure the following line is **NOT commented**

   (remove the `;` if it exists):

<pre class="overflow-visible! px-0!" data-start="976" data-end="1012"><div class="contain-inline-size rounded-2xl corner-superellipse/1.1 relative bg-token-sidebar-surface-primary"><div class="sticky top-[calc(--spacing(9)+var(--header-height))] @w-xl/main:top-9"><div class="absolute end-0 bottom-0 flex h-9 items-center pe-2"><div class="bg-token-bg-elevated-secondary text-token-text-secondary flex items-center gap-4 rounded-sm px-2 font-sans text-xs"></div></div></div><div class="overflow-y-auto p-4" dir="ltr"><code class="whitespace-pre! language-ini"><span><span>extension</span><span>=php_openssl.dll</span></span></code></div></div></pre>


---

## 🖥️ Backend Setup (Laravel)

3. Start **XAMPP** and make sure:
   * Apache ✅
   * MySQL ✅
4. Install PHP dependencies:
   ```bash
   composer install
   ```
5. Install Node.js dependencies:
   ```bash
   npm install
   ```
6. Run database migrations:
   ```bash
   php artisan migrate
   ```

---

## 🔑 Laravel Passport Setup

7. Generate a personal access client:

   ```bash
   php artisan passport:client --personal
   ```

   > Fill in the required client information when prompted.
   >
8. If step 7 fails, run:

   ```bash
   php artisan passport:install
   ```

   Then retry step 7.
9. Generate cert

   ```
   php artisan ca:generate-keys
   ```

---

## 🐍 Python Services Setup

9. Open  **two (2) separate terminal windows** .
10. Install Python dependencies:

```bash
pip install -r requirements.txt
```

11. Navigate to the Python directory:

```bash
cd app/python
```

12. Run the OCR API service:

```bash
python ocr_api.py
```

13. Run the Signer API service:

```
python signer_api.py
```

ℹ️ Keep this terminal running.

---

## 🌐 Frontend & Application Servers

13. In a new terminal, start the frontend development server:

```bash
npm run dev
```

14. In another terminal, start the Laravel development server:

```bash
php artisan serve
```

---

## ✅ All Set!

Once all services are running:

* Backend API is available via Laravel
* OCR & Signer service is running via Python
* Frontend is accessible via the dev server
