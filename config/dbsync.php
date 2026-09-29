<?php

return [

    'run_migrations' => env('DBSYNC_RUN_MIGRATIONS', false),

    'drivers' => [
        'sqlite'  => 'sqlite',
        'mysql'   => 'mysql',
        'mariadb' => 'mariadb',
        'pgsql'   => 'pgsql',
        'sqlsrv'  => 'sqlsrv',
        'oracle'  => 'oracle,oci8',
        'db2'     => 'db2,db2_ibmi_odbc,db2_ibmi_ibm,db2_zos_odbc,db2_expressc_odbc',
    ],

];
