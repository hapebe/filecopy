filecopy - developed and maintained by hpb@erp-berlin.com on behalf of ERP GmbH (http://www.erp-berlin.com), starting as early as 2002.

It's a versioned and keep-deleted file backup system based on PHP and MySQL.

The package basically synchronizes the contents of a source and target file system; but it also keeps track of files and their revisions in a database and in file storages for outdated versions of modified files and shadow copies of deleted files.

The top-level directory layout in a filecopy target file system will be as follows:
* current - contains a copy of the source file system
* archived - contains outdated versions of modified files.
* deleted - contains shadow copies of files that were deleted (but a backup copy of them existed at some time).

## Set-up
filecopy relies on MySQL and PHP on the host system. Simply copy the files to a directory of your choice (inside a www root might be appropriate).
* import sql/filecopy-structure.sql as MySQL root
* create a corresponding MySQL user with privileges for only that database
* modify cfg/config.inc.php to suit your needs - this file defines the database connection, the actual backup "sets" and other optionally important stuff

## Passwords and other private data in files
There are many places containing potential security threats in a configures of filecopy - make sure you delete private data before exporting the files of the system and making it available to 3rd parties:
* backup.sh / backup.bat - they may contain credentials used to connect to source and target filesystems.
* cfg/config.inc.php 
  * contains username and password(!) for MySQL, 
  * contains username and password(!) for SMTP for sending report e-mails,
  * contains file system paths and file names you might want to keep private.
* sql/dump-database.sh - contains username and password(!) for MySQL
* sql/* - all SQL dump files (if you generated any!) contain data about the files you included in the backup process at least at one point of time.
* logs/* - potentially contains private data about files and paths.
