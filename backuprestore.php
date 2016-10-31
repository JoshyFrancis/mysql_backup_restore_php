<?php
/* 
 * Mysql database backup/restore system in PHP
 * @author Joshy Francis <http://www.atomcircle.com>
 * @version 1.0
 * 
 * MIT License
 * 
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
 * 		All other features can be seen in client side
 */

// Report errors
error_reporting(E_ERROR | E_PARSE | E_NOTICE);	
		$host=HOST;//eg:localhost
		$username=USER;//self explanatory
		$passwd=PASSWORD;//self explanatory
		$charset='utf8';//charset keeps preferred character encoding 
		$port='3306';//self explanatory			
		$dateformat='Y-MM-d-h-i-s-A';// will be appended to database backup file name eg:test_2016-OctOct-25-10-30-54-AM.zip

		//small utility function to detect whether secured server or not
		function isSiteSSL() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443; }
		$url=(isSiteSSL()?'https://': 'http://') . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		 
		$url=str_replace('?'.$_SERVER['QUERY_STRING'],'',$url);//removing query string to get exact url
		$url2=$url;//$url2 used for building download url
		$url.='?route=backuprestore'; // or backuprestore.php?
		
		$pageincluded=true;//in case this page is included from master page
		
		$rootpath= str_replace('\\','/',__DIR__);/* this replace is for windows*/
		
		$backup_path=$rootpath.'/backup'  ;
		$upload_path=$rootpath.'/uploads' ;//can be same as backup_path
			
		if (!is_dir($backup_path)) {//if backup_path doesn't exist create it
			mkdir($backup_path, 0777);
			chmod($backup_path, 0777);
		}

		if (!is_dir($upload_path)) {//if upload_path doesn't exist create it
			mkdir($upload_path, 0777);
			chmod($upload_path, 0777);
		}
		
/* *********************** Begin Handling File Upload ********************************** */
	if(isset($_REQUEST['fileupload']) && $_REQUEST['fileupload']=='true' ){
			$uploadedfile ='';	//uploaded file name	
		if(isset($_POST['head'])){
			switch ($_POST['head']) {//used to distinguish different server requests in same page
				case 'uploadfile':
					$uploadfile = $upload_path.'/' . basename($_FILES['userfile']['name']);
					if ( substr( strtolower($uploadfile),count($uploadfile)-5)=='.zip' && move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
						chmod($uploadfile, 0777);
						$uploadedfile=basename($_FILES['userfile']['name']);
						echo "File is valid, and was successfully uploaded.\n";
					} else {
						echo "File upload failed!\n";
					}
				break;				
			}
		}
		//form posts to this page itself
?>
		<form enctype="multipart/form-data" action="<?php echo $url.'&fileupload=true&mode=upload'; ?>" method="POST">
			<!-- MAX_FILE_SIZE must precede the file input field -->
			<input type="hidden" name="MAX_FILE_SIZE" value="200000000" />
			<input type="hidden" name="mode" value="rst" />
			<input type="hidden" name="head" value="uploadfile" />
			<input name="userfile" type="file" />
			<input type="submit" value="Upload File" />
			<script>
				window.parent.uploadedfile='<?php echo $uploadedfile; ?>';
				if(window.parent.onuploadedfile){
					window.parent.onuploadedfile(window.parent.uploadedfile);
				}
			</script>
		</form>
	<?php
		exit;
	}
/* *********************** End Handling File Upload ********************************** */
	/* 
	 * function - backup
	 * This function reads all data with structure from the database based on the supplied information.
	 * It does'nt read all data at once. Reading has a defined limit. 
	 * Reading step by step controlled by/from Javascript
	 * This prevents PHP from going beyond Maximum Execution Time.
	 * dbName - self explanatory
	 * table  - self explanatory
	 * startindex - offset, keeps row index of a table 
	 * limit -self explanatory
	 * ti - table index of databse
	 * backupfile  - self explanatory
	 * count - number of rows in table
	 * */
    function backup($dbName,$table,$startindex,$limit,$ti=0,$backupfile,$count=0){
			global $host,$username,$passwd,$charset,$port,$backup_path;
			$sql='';
			
			$zip = new ZipArchive();
			$filename = $backup_path.'/'.$backupfile.'.zip';

		if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
			return "Cannot create file\n.";
		}		
        try{
			$conn = mysqli_connect($host, $username, $passwd, $dbName ,$port);
					if(!$conn){
						return  'Could not connect ';
					}
				if (!mysqli_set_charset($conn,$charset)){
					mysqli_query($conn,'SET NAMES '.$charset);
				}
			if($startindex==0 && $ti==0){
				$zip->addFromString('database.txt', $dbName );	
				
				$sql = 'CREATE DATABASE IF NOT EXISTS  '.$dbName." ;\n";
				$sql .= 'USE  '.$dbName." ;\n";
				$zip->addFromString($dbName.".sql", $sql );
				$sql = '';
				$zip->addFromString('limit_info.txt', $limit );		
				
				$result = mysqli_query($conn,'SHOW TABLES');
					$sql='';
					$i=0;
                while($row = mysqli_fetch_row($result)){
						if($i>0){
							$sql.=',';
						}						
					 	$sql.= $row[0] ;
                    $i+=1;
                }
				$zip->addFromString('tables.txt', $sql );	                
                $sql = '';

			}
			$table='`'.$table.'`';
            if($startindex==0){
                $sql .= 'DROP TABLE IF EXISTS '.$table.';';
                $row2 = mysqli_fetch_row(mysqli_query($conn,'SHOW CREATE TABLE '.$table));
                $sql.= "\n". $row2[1].";\n";
				$zip->addFromString($table.".sql", $sql );
				$sql = '';	
				$zip->addFromString($table.".txt", $count );
				$sql = '';				
			}   			
                $result = mysqli_query($conn,'SELECT * FROM '.$table.' limit '.$limit.' OFFSET '.$startindex );
                $numFields = mysqli_num_fields($result);
                    while($row = mysqli_fetch_row($result)){
                        $sql .= 'INSERT INTO '.$table.' VALUES(';
                        for($j=0; $j<$numFields; $j++){
                            if (!is_null($row[$j]) && $row[$j]!=null ){				
                                $sql .= '"'.mysqli_real_escape_string($conn,$row[$j]).'"' ;
                            }else{
                                $sql.= 'NULL';
                            }
                            if ($j < ($numFields-1)){
                                $sql .= ',';
                            }
                        }
                        $sql.= ");\n";
                    }
				$zip->addFromString($table.'/offset'.$startindex.".sql", $sql );
				$sql = '';	
            mysqli_close($conn);
				$zip->close();
				chmod($filename, 0777);
        }catch (Exception $e){
			return var_dump($e->getMessage());
        }
        return   true ;
    }
	/* 
	 * function - restoredata
	 * This function restore a database from the backup.
	 * It does'nt write all data at once. Writing has a defined limit. 
	 * Writing step by step controlled by/from Javascript
	 * This prevents PHP from going beyond Maximum Execution Time.
	 * uploadedfile - backup file
	 * ustartindex - offset, keeps row index of a table 
	 * uti - table index of databse
	 * ulen - number of tables in database	 
	 * ucount - number of rows in table
	 * name - table name	
	 * limit -self explanatory
	 * restoremethod -if '0' restore only data , if '1' recreate databse,drop and recreate tables and restore data
	 * */
    function restoredata($uploadedfile,&$ustartindex,&$uti,&$ulen,&$ucount,&$name,&$limit,$restoremethod){
			global $host,$username,$passwd,$charset,$port,$upload_path,$backup_path;
			$sql='';
			
			$zip = new ZipArchive();
			$filename = $upload_path.'/' .$uploadedfile;
			if(!is_file($filename)){
				$filename = $backup_path.'/' .$uploadedfile;
			}
		if ($zip->open($filename)!==TRUE) {
			return "Cannot open file\n.";
		}
		
        try{
				$dbName=$zip->getFromName('database.txt');
			if($restoremethod=='0'){
				$conn = mysqli_connect($host, $username, $passwd, $dbName ,$port);
			}else{
				if($uti==0 && $ustartindex==0 ){
					$conn = mysqli_connect($host, $username, $passwd, 'mysql' ,$port);
				}else{
					$conn = mysqli_connect($host, $username, $passwd, $dbName ,$port);
				}
			}
					if(!$conn){
						return  'Could not connect '.   mysqli_error($conn);
					}
				if (!mysqli_set_charset($conn,$charset)){
					mysqli_query($conn,'SET NAMES '.$charset);
				}
				$tables=$zip->getFromName('tables.txt');
				if($tables===false){
					$zip->close();
					return false;
				}
				$limit=intval($zip->getFromName('limit_info.txt'));
				$tables=explode(',',$tables);
				$ulen=count($tables);
			if($uti<$ulen){
					if($uti==0 && $ustartindex==0 ){												
						if( $restoremethod=='1'){
							$sql=$zip->getFromName( $dbName.'.sql');
							if($sql!==false){
									$sql=explode(';'.chr(10) ,$sql);
								for($i=0;$i<count($sql)-1 ;$i++){
									 $result=mysqli_query($conn,$sql[$i].';');		
									if(!$result){
										return 'Could not run query 1: '.   mysqli_error($conn);
									}		
								}	
							}
							mysqli_close($conn);
							$conn = mysqli_connect($host, $username, $passwd, $dbName ,$port);
							if(!$conn){
								return  'Could not connect ';
							}
							for($i=0;$i<$ulen ;$i++){
									$name=$tables[$i];
									$table='`'.$name.'`';
									$sql=$zip->getFromName( $table.'.sql');
								if($sql!==false){
										$sql=explode(';'.chr(10) ,$sql);
									for($j=0;$j<count($sql)-1;$j++){
										 $result=mysqli_query($conn,$sql[$j].';');									  
										if(!$result){
											return 'Could not run query 2: '.  mysqli_error($conn);
										}
									}	
								}
							}
						}
					}
						mysqli_autocommit($conn, FALSE);
						mysqli_begin_transaction ($conn, MYSQLI_TRANS_START_READ_ONLY );
				$name=$tables[$uti];
				$table='`'.$name.'`';
				$ucount=intval($zip->getFromName($table.'.txt'));
				$sql= $tables=$zip->getFromName($table.'/offset'.$ustartindex.'.sql');
				if($sql!==false){
					$sql=explode(';'.chr(10) ,$sql);
					for($i=0;$i<count($sql)-1;$i++){
						 $result=mysqli_query($conn,$sql[$i].';');				
					}								
					$ustartindex+=$limit;
				}else{
					$uti+=1;
					$ustartindex=0;
				}
				if (!mysqli_commit($conn)) {
					return "Transaction commit failed";
				}				
			}

            mysqli_close($conn);
				$zip->close();
        }catch (Exception $e){
			return var_dump($e->getMessage());
        }
        return   true ;
    }
      
	if(isset($_POST['head'])){
		/* Manages all server requests from Javascript */
			switch ($_POST['head']) {//used to distinguish different server requests in same page
				case 'backup':
					$res=backup($_POST['db'],$_POST['name'],$_POST['startindex'],$_POST['limit'],$_POST['ti'],$_POST['backupfile'],$_POST['count']);
					if($res!==true){
						echo $res;
					}else{
						echo '1';
					}
				break;
				case 'databases':
					$conn = mysqli_connect($host, $username, $passwd, 'information_schema' ,$port);
							if(!$conn){
								return  'Could not connect ';
							}
					$result = mysqli_query($conn,"SELECT TABLE_SCHEMA,TABLE_NAME from `TABLES` where TABLE_TYPE='BASE TABLE' and TABLE_SCHEMA<>'mysql' and TABLE_SCHEMA<>'performance_schema' ");
							$sql='';
							$i=0;
							$db='';
							$conn2=null;
							
						while($row = mysqli_fetch_assoc($result)){
								if($i>0){
									$sql.=',';
								}
								if($db!=$row['TABLE_SCHEMA']){
									$db=$row['TABLE_SCHEMA'];
									$conn2 = mysqli_connect($host, $username, $passwd, $db ,$port);
								}
								$count=0;
									if($conn2 ){
										$row2 = mysqli_fetch_row( mysqli_query($conn2,'SELECT IFNULL(count(*),0) as num_records FROM `'.$row['TABLE_NAME'].'`'));
										if($row2){
											$count=$row2[0];
										}
									}
								$sql.= $row['TABLE_SCHEMA'] .':'.$row['TABLE_NAME'].':'.$count;
							$i+=1;
						}
					mysqli_free_result($result);
					mysqli_close($conn);
					echo $sql;
				break;
				case 'backupfile':
						echo    $_POST['db'].'_'.date($dateformat, time());					
				break;
				case 'restorefile':
					$ustartindex=$_POST['ustartindex'];
					$uti=$_POST['uti'];
					$ulen=0;
					$ucount=0;
					$limit=0;
					$name='';
					$res=restoredata($_POST['uploadedfile'],$ustartindex,$uti,$ulen,$ucount,$name ,$limit,$_POST['restoremethod']);
					$msg='';
					$success='false';
					if($res!==true){
						$msg= $res;
					}else{
						$success='true';
					}
					$out='';
					$out.= '[';
					$out.= '{ success: "'.$success.'" ';
					$out.= ', message : "'.$msg.'" ';
					$out.= ', ustartindex : '.$ustartindex.' ';
					$out.= ', uti : '.$uti.' ';
					$out.= ', ulen : '.$ulen.' ';
					$out.= ', ucount : '.$ucount.' ';
					$out.= ', name : "'.$name.'" ';
					$out.= ', limit : "'.$limit.'" ';
					$out.= '}';
					$out.= ']';
					echo $out;
				break;	
				case 'backuplist':
						// Get files
						$files = glob($backup_path . '/' .  '*.{zip,ZIP}', GLOB_BRACE);
						$files2 = glob($upload_path . '/' .  '*.{zip,ZIP}', GLOB_BRACE);

						if (!$files) {
							$files = array();
						}
						if (!$files2) {
							$files2 = array();
						}
						$files = array_merge($files, $files2);
						// Comparison function
						function date_cmp($a, $b) {
							$a=filemtime($a);
							$b=filemtime($b);					
							if ($a == $b) {
								return 0;
							}
							return ($a > $b) ? -1 : 1;
						}						
						//uasort($files, 'date_cmp');//if array have indexes
						usort($files, 'date_cmp');//non indexed array
						for($i=0;$i<count($files);$i++){
							$fsize=' (larger than 2GB)';
							try{
								$fsize=filesize($files[$i])/1024;
								if($fsize>1024){
									$fsize=' ('.number_format($fsize/1024,2).' MB)';
								}else{
									$fsize=' ('.number_format($fsize ,2).' KB)';
								}
							}catch (Exception $e){
								//var_dump($e->getMessage());
								$fsize=' (larger than 2GB)';
							}
							echo '<a href="javascript:window.uploadedfile=\''.basename($files[$i]).'\';window.onuploadedfile();" >'.basename($files[$i]).$fsize.'</a> ';						
							$p_path=basename( dirname($files[$i])).'/';
							echo ' | <a href="'.$url2.$p_path.basename($files[$i]).'" target="_blank" >Download</a> ';
							echo ' | <a href="javascript:delfile(\''.basename($files[$i]).'\');" >Delete</a> ';						
							
							echo '<br>';
						}
				break;							
			case 'delfile':
					$file=$_POST['file'];
						$filename = $upload_path.'/' .$file;
				if(!is_file($filename)){
					$filename = $backup_path.'/' .$file;
				}
				if(unlink($filename)){
					echo '1';
				}else{
					echo '0';
				}
				break;
			}
			exit;
		}	
?>
<?php
	if($pageincluded==false){
	?>
<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
<meta charset="UTF-8" />
<title>Mysql Backup/Restore</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
		<script src="../js/jquery-1.11.1.min.js"></script>
</head>
<body>
	<?php
	}
?>
<div id="content">
	  <div class="container-fluid">
			<div id="progressinfo" style="position:relative;width:100%;padding:1px 1px 1px 1px;">
				Waiting...
			</div>
		<div id="progresscontainerdiv" style="background-color:darkgray;position:relative;width:100%;height:30px;padding:1px 1px 1px 1px;border:1px solid black;">
			<div id="progressdiv" style="background-color:#FBAC06;position:relative;width:0%;height:27px;">
			</div>
			<div id="progresperdiv" style="position:absolute;top:5px;left:50%;font-size:14px;">
				0%
			</div>
		</div>		
		<br>
		<table border=1>
			<tr>
				<td>
					<b>Backup</b>
				</td>
				<td>
					
				</td>
				<td>
					<b>Restore</b>
				</td>
			</tr>
			<tr>
				<td>
						Database
						<select id="databases">						
						</select>
					<br>					
						Speed<input type="textt" value="100" id="recordlimit" /> Records/Process
					<br>	
					  <input type="button" value="Backup" onclick="backup();" />
					<br>		  
					  <div id="backupdata"></div>					
				</td>
				<td>					
				</td>
				<td>
						Upload File
						<br>
						<iframe src="<?php echo $url.'&fileupload=true&mode=upload'; ?>" frameborder=0  scrolling="no" >
						</iframe>
					<br>	
						or select from backup already done.
						<br>
						<div id="backuplist" style="width:auto;height:80px;overflow:auto">
						</div>
					<br>
						Method
						<select id="restoremethod">
							<option selected>Data Only</option>		
							<option>Structure and Data</option>							
						</select>
					<br>
						<div id="selfile"></div>
					<br>									
					  <input type="button" value="Restore" onclick="restore();" />
					<br>	
						  
					  <div id="restoredata"></div>					
				</td>				
			</tr>
		</table>  
		  <script>
				var progressdiv=document.getElementById('progressdiv');
				var progresperdiv=document.getElementById('progresperdiv');
				var progressinfo=document.getElementById('progressinfo');
				var databases=document.getElementById('databases');				
				var backupdata=document.getElementById('backupdata');
				var recordlimit=document.getElementById('recordlimit');			
				window.uploadedfile='';	
				var restoremethod=document.getElementById('restoremethod');	
				var restoredata=document.getElementById('restoredata');					
				window.onuploadedfile=function(){
					backuplist();
					document.getElementById('selfile').innerHTML='Selected File : ' + window.uploadedfile;
				};
				
			function progress(per){		
				if(!isNaN(per)){
					progressdiv.style.width=per+'%';
					progresperdiv.innerHTML=per+'%';
				}
			}
				var ti=0;
				var tables=[];
				var backupfile='';
					function getdatabases(){
						$.post(url+ '?route=backuprestore' ,
						{
							mode:'bkp'
							,head:'databases'
						},
						function(data, status){							
							var ar=data.split(',');
							var dbs={};
							for(var i=0;i<ar.length;i++){
								var vals=ar[i].split(':');
									var db=vals[0];
									var tbl=vals[1];
									var cnt=parseInt( vals[2]);
								if(!dbs[db]){
									dbs[db]=[];
								}
								dbs[db].push({name:tbl,count:cnt,startindex:0});
							}
							for(var i=databases.options.length-1;i>=0;i--){
								databases.options.remove(i);
							}
							for(var a in dbs){
								var opt=document.createElement('option');
									opt.value=JSON.stringify(dbs[a]);
									opt.text=a;
								databases.options.add(opt);
							}
							databases.onchange=function(){
								getbackupfile();
							};
							if(databases.options.length>0){
								databases.selectedIndex=0;
								databases.onchange();
							}
						});	
					}				
					function getbackupfile(){
						var opt=databases.options[databases.selectedIndex];
						ti=0;
						tables=JSON.parse(opt.value);
						$.post(url+ '?route=backuprestore' ,
						{
							mode:'bkp'
							,head:'backupfile'
							,db:opt.text
						},
						function(data, status){							
							backupfile=data;
						});	
					}
					
				setTimeout(function(){
					getdatabases();
				},1000);
				var started=false,startTime;
			function backup(){
					window.onbeforeunload = function (e) {
						e = e || window.event;
						// For IE and Firefox prior to version 4
						if (e) {
							e.returnValue = 'Sure to cancel this process?';
						}
						// For Safari
						return 'Sure to cancel this process?';
					};
				if(ti<tables.length){
					recordlimit.setAttribute('disabled','disabled');
					var limit=parseInt( recordlimit.value);
						if(isNaN(limit)){
							limit=100;
							recordlimit.value=limit;
						}
					if(tables[ti].startindex<tables[ti].count){
						startTime=(new Date()).getTime();							
						progress(parseInt(tables[ti].startindex/(tables[ti].count)*100));
						progressinfo.innerHTML='table ' + tables[ti].name ;						
						progressinfo.innerHTML+=' processing ' + tables[ti].startindex + ' of ' + tables[ti].count + ' record(s). '  ;
						
							$.post(url+ '?route=backuprestore' ,
							{
								mode:'bkp'
								,head:'backup'
								,db:databases.options[databases.selectedIndex].text
								,ti:ti
								,name: tables[ti].name
								,startindex: tables[ti].startindex
								,limit:limit
								,backupfile:backupfile
								,count:tables[ti].count
							},
							function(data, status){
								elapsed=(new Date()).getTime()-startTime;						
							var esimatedTime=  Math.round( ( ( (tables[ti].count-tables[ti].startindex) / limit)*elapsed )/1000,2 );
		
								if(data=='1'){
									tables[ti].startindex+=limit ;
									backup();									
								}else{
									backupdata.innerHTML+='<br>'+data;
								}
								progressinfo.innerHTML+=  esimatedTime + ' second(s) remaining' ;
								
							});											
					}else{
						
						progress(0);
						ti+=1;
						backup();
					}
				}else{
					started=false;
					progressinfo.innerHTML='Processing finished.' ;
					progress(0);
					recordlimit.removeAttribute('disabled');

					backupdata.innerHTML+='<br>Download Backup file <a href="<?php echo $url2.'backup'; ?>/' + backupfile + '.zip" target="_blank">' + backupfile + '</a> ';
							getbackupfile();
						backuplist();
					window.onbeforeunload=null;							
				}
			}
			
			function backuplist(){
				$.post(url+ '?route=backuprestore' ,
				{
					mode:'lst'
					,head:'backuplist'
				},
				function(data, status){
					document.getElementById('backuplist').innerHTML=data;
				});			
			}
				setTimeout(function(){
					backuplist();
				},1000);
			var uti=0;
			var ulen=1;
			var ustartindex=0;
			var ucount=0;
			function restore(){
				if(window.uploadedfile==''){
					alert('Please upload file');
					return false;
				}
				if(uti<ulen){
					window.onbeforeunload = function (e) {
						e = e || window.event;
						// For IE and Firefox prior to version 4
						if (e) {
							e.returnValue = 'Sure to cancel this process?';
						}
						// For Safari
						return 'Sure to cancel this process?';
					};
					var startTime=(new Date()).getTime();
					$.post(url+ '?route=backuprestore' ,
					{
						mode:'rst'
						,head:'restorefile'
						,uploadedfile:window.uploadedfile
						,uti:uti
						,restoremethod:restoremethod.selectedIndex
						,ustartindex:ustartindex
					},
					function(data, status){
						data=eval(data)[0];
						if(data.success=='false'){
							restoredata.innerHTML+='<br>'+data.message;
							return false;
						}
						uti=data.uti;
						ulen=data.ulen;
						ustartindex=data.ustartindex;
						ucount=data.ucount;
						var elapsed=(new Date()).getTime()-startTime;
						var esimatedTime=  Math.round( ( ( (ucount-ustartindex) /data.limit)*elapsed )/1000,2 );
						
						progress(parseInt((ustartindex>ucount?ucount:ustartindex)/(ucount )*100));
						progressinfo.innerHTML='table ' + data.name ;						
						progressinfo.innerHTML+=' processing ' +  (ustartindex>ucount?ucount:ustartindex) + ' of ' + ucount + ' record(s). ' + esimatedTime + ' second(s) remaining' ;
						
						restore();
					});
				}else{
					progressinfo.innerHTML='Processing finished.' ;
					progress(0);
						uti=0;
						ulen=1;
						ustartindex=0;
					window.onbeforeunload=null;		
				}
			}
			function delfile(file){
				if(confirm('Delete ' + file)){
					$.post(url+ '?route=backuprestore' ,
					{
						mode:'lst'
						,head:'delfile'
						,file:file
					},
					function(data, status){
						if(data=='1'){
							window.uploadedfile='';	
							//backuplist();
							window.onuploadedfile();
						}
					});						
				}
			}
	  </script>
	</div>
</div>
<?php
	if($pageincluded==false){
	?>
	</body></html>
	<?php
	}
?>
