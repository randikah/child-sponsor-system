# child-sponsor-system
my project 

Database is should run on 3307 port
Step 1: Update phpMyAdmin's Configuration File
Open your File Explorer and navigate to:
C:\xampp\phpMyAdmin\

Look for a file named config.inc.php.

Open it with a text editor like Notepad or VS Code.

Press Ctrl + F and look for this line:

PHP
$cfg['Servers'][$i]['host'] = '127.0.0.1';
(Note: It might say 'localhost' instead of '127.0.0.1').

Change that line to include your new port number by adding a colon (:3307) to the end of the address:

PHP
$cfg['Servers'][$i]['host'] = '127.0.0.1:3307';
Save the file (Ctrl + S) and close it.

Step 2: Refresh Your Browser
Go back to your browser window showing the red error screen.

Hard-refresh the page by pressing Ctrl + F5 (or clear your browser cache).

The interface will now route through port 3307 and display your database structures cleanly!