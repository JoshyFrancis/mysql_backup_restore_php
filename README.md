# mysql_backup_restore_php
Mysql database backup/restore system in PHP

 * Requires (PHP 5 >= 5.2.0, PHP 7, PECL zip >= 1.8.0) for ZipArchive
 * Requires (PHP 5, PHP 7) for The mysqli class
 * Requires Mysql Version>=50549
 * Requires sufficient Mysql user permissions to :
 * 						- enumerate databases(not necessary) 
 * 						- create/delete tables(not necessary)
 * 						- write data(to restore database)
 * 						- read data(main purpose)
 * Requires jquery>=1.11.1 for Client side
 * 
 * Tested in all major browsers
 * Tested environment :
 * 				- Server : Apache/2.4.7 (Ubuntu)
 *              - System : PHP/5.5.9-1ubuntu4.17
 *              - Mysql  : 50549 (Version)
 * 
 * Usage :
 * 		provide Mysql HOST,USERNAME & PASSWORD
 * 		
 * MIT License
