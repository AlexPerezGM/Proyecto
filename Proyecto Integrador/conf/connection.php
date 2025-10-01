<?php
    class Connection {
        private $host = 'localhost';
        private $dbname = 'pruebas';
        private $username = 'root';
        private $password = '';

        public function connect() {

            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname}";
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                );
                return new PDO($dsn, $this->username, $this->password, $options);
            } catch(PDOException $th) {
                echo "Connection error: " . $th->getMessage();
                exit;
            }
        }
    }