<?php
/**
 * Clase SqlServerDataManager
 *
 * Clase para manejar bases de datos SQL Server.
 * Dev. Oscar E. Urdaneta
 * Fecha: 2026-04-06
 */
class SqlServerDataManager
{
    /**
     * Definicion de constantes.
     */
    private const __SQLSERVER_DATA_MANAGER_LOG_FILENAME = __DIR__ . '/log/sqlserver-data-manager.log';
    private const __SQLSERVER_DATA_MANAGER_SAVE_LOG = true;

    /**
     * Propiedades privadas.
     */
    private $connected;
    private $conn;
    private $stmt;
    private $errorMessage;
    private $saveEventLog;

    /**
     * Constructor.
     * @param array $dbInfo Arreglo asociativo con la informacion de conexion a la base de datos.
     *
     * Campos de dbInfo.
     * host: Nombre del host o direccion IP.
     * port: Puerto SQL Server (opcional).
     * prefix: Prefijo de la base de datos, se puede omitir el valor.
     * dbname: Nombre de la base de datos, se puede omitir el valor.
     * user: Nombre del usuario de la base de datos.
     * pwd: Contrase\u00f1a.
     *
     * Crea una conexion dentro de la clase, para determinar si se realizo la conexi\u00f3n se
     * debe invocar el metodo IsConnected().
     */
    function __construct($dbInfo)
    {
        // Establece los valores por defecto.
        $this->connected = false;
        $this->conn = false;
        $this->stmt = false;
        $this->errorMessage = '';
        $this->saveEventLog = SqlServerDataManager::__SQLSERVER_DATA_MANAGER_SAVE_LOG;

        // Valida la estructura del arreglo asociativo dbInfo.
        if (!$this->ValidateDbInfoStructure($dbInfo)) {
            return;
        }

        // Construye datos de conexion.
        $host = $dbInfo['host'];
        $port = isset($dbInfo['port']) ? $dbInfo['port'] : '';
        $dbname = '';
        $user = $dbInfo['user'];

        if ($dbInfo['dbname'] != '') {
            $dbname = $dbInfo['prefix'] . $dbInfo['dbname'];
            $user = $dbInfo['prefix'] . $dbInfo['user'];
        }

        $serverName = $host;
        if ($port !== '') {
            $serverName = $host . ',' . $port;
        }

        $connectionInfo = array(
            'UID' => $user,
            'PWD' => $dbInfo['pwd'],
            'CharacterSet' => 'UTF-8',
            "Encrypt" => true,
            "TrustServerCertificate" => true
        );

        if ($dbname !== '') {
            $connectionInfo['Database'] = $dbname;
        }

        // Intenta conectar con la base de datos.
        $conn = sqlsrv_connect($serverName, $connectionInfo);

        // Si fallo la conexion.
        if ($conn === false) {
            $this->errorMessage = $this->BuildSqlSrvErrorMessage();
            $this->SaveEventLog($this->errorMessage);
            return;
        }

        $this->errorMessage = '';
        $this->conn = $conn;
        $this->connected = true;
    }

    /**
     * Destructor.
     *
     * Garantiza que se cierre la conexion con la base de datos.
     */
    function __destruct()
    {
        $this->Close();
    }

    /**
     * IsConnected
     *
     * Retorna true si hay una conexi\u00f3n establecida con la base de datos, de lo contrario
     * retorna false.
     */
    public function IsConnected()
    {
        return $this->connected;
    }

    /**
     * BeginTransaction.
     *
     * Inicializa una transaccion.
     */
    public function BeginTransaction()
    {
        // Valida que exista una conexion activa.
        if (!$this->connected) {
            $this->errorMessage = 'BeginTransaction: No hay una conexi\u00f3n activa';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Valida que no hayan resultados pendientes por extraer.
        if ($this->stmt !== false) {
            $this->errorMessage = 'BeginTransaction: Hay resultados pendientes por extraer';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!sqlsrv_begin_transaction($this->conn)) {
            $this->errorMessage = 'BeginTransaction: ' . $this->BuildSqlSrvErrorMessage();
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        return true;
    }

    /**
     * CommitTransaction
     *
     * Finaliza y guarda los cambios de una transaccion.
     */
    public function CommitTransaction()
    {
        // Valida que exista una conexion activa.
        if (!$this->connected) {
            $this->errorMessage = 'CommitTransaction: No hay una conexi\u00f3n activa';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!sqlsrv_commit($this->conn)) {
            $this->errorMessage = 'CommitTransaction: ' . $this->BuildSqlSrvErrorMessage();
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        return true;
    }

    /**
     * RollbackTransaction
     *
     * Reversa los cambios realizados en una transaccion y la finaliza.
     */
    public function RollbackTransaction()
    {
        // Valida que exista una conexion activa.
        if (!$this->connected) {
            $this->errorMessage = 'RollbackTransaction: No hay una conexi\u00f3n activa';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!sqlsrv_rollback($this->conn)) {
            $this->errorMessage = 'RollbackTransaction: ' . $this->BuildSqlSrvErrorMessage();
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        return true;
    }

    /**
     * Query
     * @param string Sentencia sql a ejecutar.
     * @param int Numero maximo de registros a extraer del buffer, todos por defecto.
     *
     * Ejecuta una consulta sql simple, la consulta puede devolver resultados.
     *
     * Si falla retorna falso y establece el mensaje de error.
     */
    public function Query($sqlCommand, $maxRows = 0)
    {
        // Valida los parametros.
        if (gettype($sqlCommand) != 'string') {
            $this->errorMessage = 'Query: El parametro [sqlCommand] debe ser de tipo string';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($maxRows) != 'integer') {
            $this->errorMessage = 'Query: El parametro [maxRows] debe ser de tipo numerico';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Valida que exista una conexion activa.
        if (!$this->connected) {
            $this->errorMessage = 'Query: No hay una conexi\u00f3n activa';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Valida que no hayan resultados pendientes por extraer.
        if ($this->stmt !== false) {
            $this->errorMessage = 'Query: Hay resultados pendientes por extraer';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Ejecuta la sentencia.
        $stmt = sqlsrv_query($this->conn, $sqlCommand);

        // Si falla la ejecucion.
        if ($stmt === false) {
            $this->errorMessage = 'Query: ' . $this->BuildSqlSrvErrorMessage();
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Si no hay conjunto de resultados, finaliza correctamente.
        $metadata = sqlsrv_field_metadata($stmt);
        if ($metadata === false) {
            sqlsrv_free_stmt($stmt);
            $this->errorMessage = '';
            return true;
        }

        // Extrae los registros en un arreglo asociativo.
        $this->stmt = $stmt;
        $data = $this->Fetch($maxRows);

        // Devuelve los resultados.
        return $data;
    }

    /**
     * Metodo Fetch
     * @param number Numero maximo de registros a recuperar.
     *
     * Carga registros desde el buffer de una consulta previa.
     *
     * Retorna un arreglo asociativo con los registros o false si falla.
     */
    public function Fetch($maxRows = 0)
    {
        // Valida los parametros.
        if (gettype($maxRows) != 'integer') {
            $this->errorMessage = 'Query: El parametro [maxRows] debe ser de tipo numerico';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Valida que exista una conexion activa.
        if (!$this->connected) {
            $this->errorMessage = 'Query: No hay una conexi\u00f3n activa';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Extrae los registros en un arreglo asociativo.
        $i = 0;
        $data = array();

        // Valida que existan resultados pendientes por extraer.
        if ($this->stmt === false) {
            return $data;
        }

        while (true) {
            // Toma el registro.
            $row = sqlsrv_fetch_array($this->stmt, SQLSRV_FETCH_ASSOC);

            // Si fallo.
            if ($row === false) {
                $this->errorMessage = 'Fetch: ' . $this->BuildSqlSrvErrorMessage();
                $this->SaveEventLog($this->errorMessage);
                return false;
            }

            // Si no hay mas registros por extraer.
            if (is_null($row)) {
                sqlsrv_free_stmt($this->stmt);
                $this->stmt = false;
                break;
            }

            // Normaliza los nombres de campos (keys) a minusculas.
            $row = array_change_key_case($row, CASE_LOWER);

            // Guarda el registro en el arreglo.
            $data[$i] = $row;
            $i++;

            // Si alcanzo el maximo de registros solicitados.
            if ($maxRows != 0 && $i == $maxRows) {
                break;
            }
        }

        $this->errorMessage = '';

        // Devuelve los resultados.
        return $data;
    }

    /**
     * Metodo GetDatabases.
     *
     * Devuelve un arreglo con la lista de bases de datos.
     */
    public function GetDatabases()
    {
        $sqlCommand = "SELECT name FROM sys.databases ORDER BY name;";
        $result = $this->Query($sqlCommand);

        if ($result === false) {
            return $result;
        }

        $i = 0;
        $data = array();
        for ($j = 0; $j < count($result); $j++) {
            foreach ($result[$j] as $key => $value) {
                $data[$i] = strtolower($value);
                $i++;
                break;
            }
        }

        $this->errorMessage = '';
        return $data;
    }

    /**
     * Metodo ExistDatabase.
     *
     * @param string Nombre de la base de datos que se desea buscar.
     *
     * Devuelve true si existe la base de datos indicada en la base de datos, de lo
     * contrario retorna false.
     */
    public function ExistDatabase($name)
    {
        // Valida los parametros.
        if (gettype($name) != 'string') {
            $this->errorMessage = 'ExistDatabase: El tipo de [name] debe ser string';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Toma la lista de tablas registradas.
        $result = $this->GetDatabases();

        if ($result === false) {
            return $result;
        }

        $found = false;
        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i] == $name) {
                $found = true;
                break;
            }
        }

        $this->errorMessage = '';
        return $found;
    }

    /**
     * Metodo GetTables.
     *
     * Devuelve un arreglo con las tablas que contiene la base de datos.
     */
    public function GetTables()
    {
        $sqlCommand = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE';";
        $result = $this->Query($sqlCommand);

        if ($result === false) {
            return $result;
        }

        $i = 0;
        $data = array();
        for ($j = 0; $j < count($result); $j++) {
            foreach ($result[$j] as $key => $value) {
                $data[$i] = strtolower($value);
                $i++;
                break;
            }
        }

        $this->errorMessage = '';
        return $data;
    }

    /**
     * Metodo ExistTable.
     *
     * @param string Nombre de la base de tabla que se desea buscar.
     *
     * Devuelve true si existe la tabla indicada existe en la base de datos, de lo
     * contrario retorna false.
     */
    public function ExistTable($name)
    {
        // Valida los parametros.
        if (gettype($name) != 'string') {
            $this->errorMessage = 'ExistTable: El tipo de [name] debe ser string';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        // Toma la lista de tablas registradas.
        $result = $this->GetTables();

        if ($result === false) {
            return $result;
        }

        $found = false;
        for ($i = 0; $i < count($result); $i++) {
            if ($result[$i] == $name) {
                $found = true;
                break;
            }
        }

        $this->errorMessage = '';
        return $found;
    }

    /**
     * Metodo Close.
     *
     * Termina la conexion con la base de datos.
     */
    public function Close()
    {
        if ($this->connected) {
            // Si hay resultados pendientes.
            if ($this->stmt !== false) {
                sqlsrv_free_stmt($this->stmt);
                $this->stmt = false;
            }

            // Termina la conexion.
            sqlsrv_close($this->conn);
            $this->conn = false;
            $this->connected = false;
        }
    }

    /**
     * Metodo GetErrorMessage.
     *
     * Devuelve el ultimo mensaje de error registrado.
     * @return string
     */
    public function GetErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Metodo SetEventLogState
     * @param bool $state Valor del estado que se desea establecer.
     *
     * Establece si se debe guardar el registro de eventos en el archivo, por
     * defecto es true
     */
    public function SetEventLogState($state)
    {
        if (gettype($state) != 'boolean') {
            $this->errorMessage = 'SetEventLogState: El parametro [state] debe ser de tipo booleano';
            $this->SaveEventLog($this->errorMessage);
            return;
        }
        $this->saveEventLog = $state;
    }

    /**
     * DECLARACION DE METODOS PRIVADOS.
     */

    /**
     * ValidateDbInfoStructure
     * @param array $dbInfo Array con la informacion de conexion.
     *
     * Valida la estructura del arreglo asociativo dbInfo.
     *
     * Retorna true si todo esta correcto o false de lo contrario, si algo no esta
     * correcto se puede obtener el mensaje de error con el metodo GetErrorMessage()
     */
    private function ValidateDbInfoStructure($dbInfo)
    {
        if (gettype($dbInfo) != 'array') {
            $this->errorMessage = 'El parametro [dbInfo] debe ser un arreglo asociativo';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!isset($dbInfo['host'])) {
            $this->errorMessage = 'No se establecio el campo [host] en el parametro [dbInfo]';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($dbInfo['host']) != 'string') {
            $this->errorMessage = 'El campo [host] debe ser de tipo cadena';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!isset($dbInfo['prefix'])) {
            $this->errorMessage = 'No se establecio el campo [prefix] en el parametro [dbInfo]';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($dbInfo['prefix']) != 'string') {
            $this->errorMessage = 'El campo [prefix] debe ser de tipo cadena';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!isset($dbInfo['dbname'])) {
            $this->errorMessage = 'No se establecio el campo [dbname] en el parametro [dbInfo]';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($dbInfo['dbname']) != 'string') {
            $this->errorMessage = 'El campo [dbname] debe ser de tipo cadena';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!isset($dbInfo['user'])) {
            $this->errorMessage = 'No se establecio el campo [user] en el parametro [dbInfo]';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($dbInfo['user']) != 'string') {
            $this->errorMessage = 'El campo [user] debe ser de tipo cadena';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (!isset($dbInfo['pwd'])) {
            $this->errorMessage = 'No se establecio el campo [pwd] en el parametro [dbInfo]';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        if (gettype($dbInfo['pwd']) != 'string') {
            $this->errorMessage = 'El campo [pwd] debe ser de tipo cadena';
            $this->SaveEventLog($this->errorMessage);
            return false;
        }

        $this->errorMessage = '';
        return true;
    }

    /**
     * BuildSqlSrvErrorMessage
     *
     * Construye una cadena con la lista de errores de sqlsrv_errors().
     */
    private function BuildSqlSrvErrorMessage()
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        if ($errors === null || $errors === false || count($errors) === 0) {
            return 'Error desconocido de SQL Server';
        }

        $messages = array();
        for ($i = 0; $i < count($errors); $i++) {
            $state = isset($errors[$i]['SQLSTATE']) ? $errors[$i]['SQLSTATE'] : 'N/A';
            $code = isset($errors[$i]['code']) ? $errors[$i]['code'] : 'N/A';
            $message = isset($errors[$i]['message']) ? trim($errors[$i]['message']) : 'Error sin descripcion';
            $messages[] = '[' . $state . '/' . $code . '] ' . $message;
        }

        return implode(' | ', $messages);
    }

    /**
     * SaveEventLog
     * @param string $log Cadena con el evento que se desea guardar.
     *
     * Guarda el registro de eventos.
     */
    private function SaveEventLog($log)
    {
        if (!$this->saveEventLog) {
            return;
        }

        // Ejemplo: puede venir relativo ("log/sqlserver-data-manager-log")
        // o absoluto ("C:\xampp\htdocs\smartsoft\saivernet\log\sqlserver-data-manager-log")
        $logFile = SqlServerDataManager::__SQLSERVER_DATA_MANAGER_LOG_FILENAME;

        // Normaliza separadores
        $logFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logFile);

        // Si NO es absoluta, prepende __DIR__
        $isAbsolute =
            preg_match('/^[A-Za-z]:\\\\/', $logFile) === 1 || // C:\...
            str_starts_with($logFile, '\\\\');                // UNC \\server\share

        if (!$isAbsolute) {
            $logFile = __DIR__ . DIRECTORY_SEPARATOR . ltrim($logFile, DIRECTORY_SEPARATOR);
        }

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $fp = @fopen($logFile, 'ab');
        if ($fp === false) {
            error_log("No se pudo abrir el log: $logFile");
            return false;
        }

        fwrite($fp, date('Y-m-d H:i:s') . ' ' . $log . PHP_EOL);
        fclose($fp);
        return true;
    }
}
