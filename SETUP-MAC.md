# Running the BGE Corporation CRM on a Mac

This folder has everything from the Windows PC: the code, your settings (`backend/.env`),
the uploaded product pictures, and a copy of the database (`backend/database/export/sari_crm.sql`).

## 1. Install the programs (once)

1. **XAMPP for macOS**, PHP 8.2 version: download from https://www.apachefriends.org and install it
   to the default place (`/Applications/XAMPP`).
2. **Node.js** LTS: download from https://nodejs.org and install it.
3. **Visual Studio Code**: https://code.visualstudio.com

## 2. Copy the project

1. Unzip `sari-crm-mac.zip`, for example into your **Documents** folder.
2. Open Visual Studio Code, choose **File > Open Workspace from File...**, and pick
   `sari-crm/sari-crm.code-workspace`. (Or **File > Open Folder...** and pick the `sari-crm` folder.)

## 3. Start the database

Open **XAMPP** (in Applications, called *manager-osx*), go to the **Manage Servers** tab,
select **MySQL Database** and click **Start**. Do this every time you restart the Mac.

## 4. Start the CRM

In Visual Studio Code press **Shift+Command+B** (or **Terminal > Run Task... > Start BGE CRM (Mac)**).

The first run imports your database and downloads the program's packages, which takes a few minutes and needs an internet connection.
When it says **Starting the CRM**, open http://localhost:5173 in your browser.

To run it from the Terminal app instead:

```bash
cd ~/Documents/sari-crm
bash start-mac.sh
```

Press **Control+C** in that terminal to stop the CRM.

## If something goes wrong

- **"MySQL is not running"**: start MySQL Database in XAMPP (step 3), then run it again.
- **"permission denied" or macOS blocks XAMPP**: open **System Settings > Privacy & Security** and click
  **Open Anyway** for XAMPP.
- **Port 3306 already in use**: another MySQL is running on the Mac. Stop it, or change the port in XAMPP
  and in `backend/.env` (`DB_PORT`).
- **Start over with the database from the PC**: in XAMPP open phpMyAdmin
  (http://localhost/phpmyadmin), delete the `sari_crm` database, then start the CRM again.

## Keep in mind

- `backend/.env` contains your Claude API key and app secret. Don't share this zip or upload it publicly.
- The Mac gets a copy of the data. New orders and customers added on the PC after the copy won't appear on
  the Mac, and the other way round. Use one computer as the main one.
