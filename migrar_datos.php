<!DOCTYPE html>
	<head>
		<title>Migrate data</title>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
		<meta name="description" content="">
	</head>
	<body lang="es">
		<div class="cuerpo">
<?php 

	include_once './functions.php';

	$dbh = connect_mysql();	
    if (!$dbh) {
        print 'Error';
        printf("Connect failed: %s<br>\n", mysql_connect_error());
        exit();
    }		

	connect_oracle();

	// filter tables to loading
	$stmtabla = "SELECT t.TABLE_NAME FROM USER_TABLES t WHERE t.TABLE_NAME NOT LIKE 'MD%' AND t.TABLE_NAME NOT LIKE 'MIG%'";
	$stid1 = oci_parse($conn, $stmtabla);

	if (!$stid1) {
	    $e = oci_error($conn);
	    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
	}

	// Perform the logic of the query para obtener los datos
	$r = oci_execute($stid1);
	if (!$r) {
	    $e = oci_error($stid1);
	    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
	}

	while ($rowt = oci_fetch_array($stid1, OCI_ASSOC+OCI_RETURN_NULLS)) {	

		print $rowt['TABLE_NAME'] . '<br>';
		$stmt = "SELECT * FROM " . $rowt['TABLE_NAME'];

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
			
			$ncols = oci_num_fields($stid);

			$campos = array();
			for ($i = 1; $i <= $ncols; $i++) {
			    $column_name  = oci_field_name($stid, $i);
			    array_push($campos, $column_name);
			}

				while ($row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS)) {
					$sql = 'INSERT INTO ' . $rowt['TABLE_NAME'] . ' (';
					$datos = array();
					$sql = $sql . implode(',',$campos) . ') values (:' . implode(',:',$campos) . ')';
					print $sql . '<br>';
					$sth = $dbh->prepare($sql);
					for ($i=1;$i <= $ncols; $i++) {
						$dato = str_replace("'",'"', getParam($row[oci_field_name($stid, $i)]));
						$dato = str_replace('"','\"', $dato);
						print 'Bind :'.$campos[($i-1)] . ' to ' . getParam($row[oci_field_name($stid, $i)]) . '<br>';
						$sth->bindParam(':'.$campos[($i-1)],getParam($row[oci_field_name($stid, $i)]));
					}
					try {
						$sth->execute();
					}
					catch (PDOException $e) {
				    	print $e->getMessage();
				    	return 'ERROR '.$e->getMessage();						  			
					}				
					

				}

		print '</div>';

	}
	oci_close($conn);			
?>
		</div>
	</body>
</html>	
