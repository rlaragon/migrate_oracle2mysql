<!DOCTYPE html>
	<head>
		<title>SIUPV. Schema migration</title>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
		<meta name="description" content="">
	</head>
	<body lang="es">
		<div class="cuerpo">
<?php 
// You can send 2 parameter using GET
// borra: defines what to do if table exists in MySql. Default N (nothing). S = Recreate table
// foreign: defines if foreign key will be created. Default N
// The easiest process is to create tables whitout foreign keys, after loading data and later creating foreign keys.

	if (isset($_GET['borra'])) {
		$tdrop = $_GET['borra'];
	}
	else {$tdrpo = 'N';}
	$cajenas = 'N';
	if (isset($_GET['foreign'])) {
		$cajenas = $_GET['foreign'];
	}

	$esquema_mysql = '<MySql schema>';
	$search = "'";
	$replace = '"';
	include_once './functions.php';

	// If tables are going to be recreate firstly I drop all foreign keys
	$dbh = connect_mysql();	
    if (!$dbh) {
        print 'Error';
        printf("Connect failed: %s<br>\n", mysql_connect_error());
        exit();
    }		

	if ($tdrop == 'S') {
		$sql = "select constraint_name, table_name FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE constraint_schema=:esquema AND table_schema=:esquema and constraint_type='FOREIGN KEY'";
		$sthfk = $dbh->prepare($sql);
		try {
			$sthfk->execute(array(':esquema'=>$esquema_mysql));

			$result = $sthfk->fetchAll(PDO::FETCH_ASSOC);
			$filas = count($result);
			for ($i=0; $i<$filas; $i++) {
				print 'Deleting ... ' . $result[$i]['table_name'] . ' - ' . $result[$i]['constraint_name'] . '<BR>';
				$sql = 'ALTER TABLE ' . $result[$i]['table_name'] . ' DROP FOREIGN KEY ' . $result[$i]['constraint_name'];
				print $sql .'<br>';
				$sthdfk = $dbh->prepare($sql);
				try {
					$sthdfk->execute();
					print 'Constraint ' . $result[$i]['constraint_name'] . ' deleted' . '<br>';
				}
				catch (PDOException $e) {
			    	print $e->getMessage();
			    	return 'ERROR '.$e->getMessage();						  			
				}
			} 
		}
		catch (PDOException $e) {
			print $e->getMessage();
			return 'ERROR '.$e->getMessage();
		}		
		print 'FOREIGN KEY delete process finished' . '<br>';
	}

	// Select Oracle tables 
	connect_oracle();

	// Change this select to filter tables you don't want to migrate
	$stmt = "SELECT t.TABLE_NAME FROM USER_TABLES t WHERE t.TABLE_NAME NOT LIKE 'MD%' AND t.TABLE_NAME NOT LIKE 'MIG%' ";

	$stid = oci_parse($conn, $stmt);

	if (!$stid) {
	    $e = oci_error($conn);
	    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
	}

	$r = oci_execute($stid);
	if (!$r) {
	    $e = oci_error($stid);
	    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
	}

	print '<div class="contenedor">';
			while ($row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS)) {
				$borrado = 0;
				print $row['TABLE_NAME'] . '<br>' ;
				// For each table check if exists in MySql

			 	$sth2 = $dbh->prepare("SELECT count(*) existe FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=:esquema_ms and TABLE_NAME=:tabla");
			 	try {
			 		$sth2->execute(array(':tabla'=>$row['TABLE_NAME'], ':esquema_ms'=>$esquema_mysql));
				 	$result = $sth2->fetch(PDO::FETCH_OBJ);
			 		$existe = $result->existe;
			 		if ($existe != 0 && $tdrop == 'S') {
			 			print 'Dropping table ' . $row['TABLE_NAME'] . '...   ';
						$sql = "DROP TABLE " . $row['TABLE_NAME'];
						$sthmysql2 = $dbh->prepare($sql);
						try {
							$sthmysql2->execute();
							print 'Tabla dropped successfully' . '<br>';
						}
						catch (PDOException $e) {
							print $e->getMessage();
							return 'ERROR '.$e->getMessage();
						}							
						$borrado = 1;		 	  			
					
			 		}
			 	  	if ($existe == 0 || $borrado == 1) {
		 			
			 			// If it doesn't exist ...

			 			$sql = "CREATE TABLE " . $row['TABLE_NAME'] . "(";

						$stmtc = "SELECT COLUMN_NAME, DATA_TYPE, DATA_PRECISION, DATA_SCALE, DATA_LENGTH, NULLABLE FROM USER_TAB_COLUMNS WHERE table_name='" . $row['TABLE_NAME'] . "' ORDER BY COLUMN_ID";

						$stidc = oci_parse($conn, $stmtc);

						if (!$stidc) {
						    $e = oci_error($conn);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}

						$r = oci_execute($stidc);
						if (!$r) {
						    $e = oci_error($stidc);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}

						$primera_col = 0;
						while ($rowc = oci_fetch_array($stidc, OCI_ASSOC+OCI_RETURN_NULLS)) {
							if ($primera_col == 0) {
								$sql = $sql . $rowc['COLUMN_NAME'] . ' ';
								$primera_col = 1;
							}
							else {
								$sql = $sql . ', ' . $rowc['COLUMN_NAME'] . ' ';	
							}
							// Here change some data types between Oracle and MySql
							switch ($rowc['DATA_TYPE']) {
								case 'VARCHAR2': 
									$datatype = 'VARCHAR';
									break;
								case 'NUMBER':
									$datatype = 'NUMERIC';
									break;
								default :
									$datatype = $rowc['DATA_TYPE'];
							}
							if ($datatype == 'VARCHAR' || $datatype == 'CHAR') {
								$datasize = '(' . $rowc['DATA_LENGTH'] . ')';
							}
							else {
								if ($rowc['DATA_PRECISION'] == '') {
									$datasize = '';	
								}
								else {
									$datasize = '(' . $rowc['DATA_PRECISION'] . ',' . $rowc['DATA_SCALE'] . ')';	
								}
							}
							$sql = $sql . $datatype . $datasize;

							if ($rowc['NULLABLE'] == 'N') {
								$sql = $sql . ' NOT NULL';
							}

							// Adding comments for columns
							$stmtcm = "SELECT COMMENTS FROM USER_COL_COMMENTS WHERE TABLE_NAME='" . $row['TABLE_NAME'] . "' and COLUMN_NAME='" . $rowc['COLUMN_NAME'] . "'";
							$stidcm = oci_parse($conn, $stmtcm);

							if (!$stidcm) {
							    $e = oci_error($conn);
							    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
							}

							oci_define_by_name($stidcm, 'COMMENTS', $col_comment);
							// Perform the logic of the query para obtener los datos
							$r = oci_execute($stidcm);
							if (!$r) {
							    $e = oci_error($stidcm);
							    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
							}

							oci_fetch($stidcm);

							$col_comment = str_replace("'",'"',$col_comment);
							$col_comment = str_replace('"','\"',$col_comment);

							$sql = $sql . " COMMENT '" . getParam($col_comment) . "'";


						}
						// Adding primary key
						$stmti = "select c.table_name, i.column_name, i.column_position from (select table_name, index_name from user_constraints where constraint_type='P') c, user_ind_columns i where c.table_name='" . $row['TABLE_NAME'] . "' and c.table_name=i.table_name and c.index_name=i.index_name order by i.column_position" ;
						$stidi = oci_parse($conn, $stmti);

						if (!$stidi) {
						    $e = oci_error($conn);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}
						$r = oci_execute($stidi);
						if (!$r) {
						    $e = oci_error($stidi);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}

						$primero = 0;
						while ($rowi = oci_fetch_array($stidi, OCI_ASSOC+OCI_RETURN_NULLS)) {
							if ($primero == 0) {
								$sql = $sql . ', PRIMARY KEY (' . $rowi['COLUMN_NAME'] ;
								$primero = 1;
							}
							else {
								$sql = $sql . ',' . $rowi['COLUMN_NAME'];
							}
						}
						if ($primero == 0) {
							$sql = $sql . ')';
						}
						else {
							$sql = $sql . '))';	
						}

						// Adding table comment

						$stmtct = "SELECT COMMENTS FROM USER_TAB_COMMENTS WHERE TABLE_NAME='" . $row['TABLE_NAME'] . "'";
						$stidct = oci_parse($conn, $stmtct);

						if (!$stidct) {
						    $e = oci_error($conn);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}
						oci_define_by_name($stidct, 'COMMENTS', $tab_comment);
						$r = oci_execute($stidct);
						if (!$r) {
						    $e = oci_error($stidct);
						    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
						}
						oci_fetch($stidct);
						$tab_comment = str_replace("'",'"',$tab_comment);
						$tab_comment = str_replace('"','\"',$tab_comment);

						$sql = $sql . " COMMENT '" . getParam($tab_comment) . "'";

						// Now we can create the table in MySql

						print '<br><BR>' . $sql . '<br>';

						$sql = str_replace($search, $replace, $sql);

						print '<br><BR>' . $sql . '<br>';
						print 'Creando ... ' . '<br>';

						$sthmysql2 = $dbh->prepare($sql);
					  	try {
					  		$sthmysql2->execute();
					  		print 'Table create successfully' . '<hr>';
					  	}
					  	catch (PDOException $e) {
					    	print $e->getMessage();
					    	return 'ERROR '.$e->getMessage();
					  	}						

			 	  	}

			 	  	print '<br>';
			 	}
			  	catch (PDOException $e) {
			    	print $e->getMessage();
			    	return 'ERROR '.$e->getMessage();
			  	}

			}
 
 		// Now, when all tables and primary key are created, we can define foreign keys
		$stmt = "SELECT DISTINCT t.TABLE_NAME, c.CONSTRAINT_NAME FROM USER_TABLES t, USER_CONSTRAINTS c WHERE t.TABLE_NAME NOT LIKE 'MD%' AND t.TABLE_NAME NOT LIKE 'MIG%' and t.table_name=c.table_name and c.constraint_type='R'";
		$stid = oci_parse($conn, $stmt);

		if (!$stid) {
		    $e = oci_error($conn);
		    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
		}

		// Perform the logic of the query para obtener los datos
		$r = oci_execute($stid);
		if (!$r) {
		    $e = oci_error($stid);
		    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
		}

		if ($cajenas == 'S') {
			print '<div class="contenedor">';

			print '<br>';
			while ($row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS)) {
				print $row['TABLE_NAME'] . ' -->  ' . $row['CONSTRAINT_NAME'] . '<BR>';

				$stmtr = "select 'FOREIGN KEY ('||rl.column_name||') REFERENCES '||rr.table_name||'('||rr.column_name||')' CONSTRAINT from user_constraints c, user_cons_columns rl, user_cons_columns rr where c.constraint_type='R' and c.table_name='" . $row['TABLE_NAME'] . "' and c.constraint_name=rl.constraint_name and c.r_constraint_name=rr.constraint_name AND c.constraint_name='" . $row['CONSTRAINT_NAME'] . "'";

				$stidr = oci_parse($conn, $stmtr);

				if (!$stidr) {
				    $e = oci_error($conn);
				    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
				}

				// Perform the logic of the query para obtener los datos
				oci_define_by_name($stidr, 'CONSTRAINT', $fk);				
				$r = oci_execute($stidr);
				if (!$r) {
				    $e = oci_error($stidr);
				    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
				}

				oci_fetch($stidr);

				$sql = 'ALTER TABLE ' . $row['TABLE_NAME'] . ' ADD ' . $fk;
				print 'Running ... ' . $sql . ' ... ';
				$sthmysql2 = $dbh->prepare($sql);
					  	try {
					  		$sthmysql2->execute();
					  		print 'Table altered successfully ' . '<br>';
					  	}
					  	catch (PDOException $e) {
					    	print $e->getMessage();
					    	return 'ERROR '.$e->getMessage();
					  	}		
				print '<hr>';
			}

			print '</div>';
		}

		oci_close($conn);
		?>
		</div>
	</body>
</html>	
