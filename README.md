migrate_oracle2mysql
====================

PHP to move tables and data from Oracle database to MySql


Process is designed in three steps:

1. Migrate data structure using migrar_bd.php. Use it with borrar=S and foreign=N
2. Load data using migrar_datos.php. Foreign key can't exists before loading data
3. Create foreing keys usando migrar_bd?foreign=S

It is possible create the structure with foreign keys 
   migrar_bd?borra=S&foreign=S
   
   
To do:

1. Add check constraints
2. Add indexes
 
