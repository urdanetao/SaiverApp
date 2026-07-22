<?php
require_once __DIR__ . '/session-manager.php';
require_once __DIR__ . '/mysql-data-manager.php';
require_once __DIR__ . '/sqlserver-data-manager.php';
require_once __DIR__ . '/dbinfo.php';

define('SAIVERAPP_DB', 'smartsoft_saiverapp');
define('SAINT_DB', 'saiverdb');

function escapeSqlLiteral($value)
{
    return str_replace("'", "''", (string) $value);
}

function getUserIdFromToken($token)
{
    $sessionManager = new SessionManager();
    $session = $sessionManager->getSession($token);
    if ($session === null || !isset($session['user']['id'])) {
        return null;
    }
    return (int) $session['user']['id'];
}

function login($params)
{
    $nickname = isset($params['nickname']) ? trim((string) $params['nickname']) : '';
    $pwd = isset($params['pwd']) ? (string) $params['pwd'] : '';

    if ($nickname == '') {
        return getResultObject(false, 'Debe indicar el nombre de usuario');
    }

    if ($pwd == '') {
        return getResultObject(false, 'Debe indicar la contraseña');
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $nickname)) {
        return getResultObject(false, 'Nombre de usuario inválido');
    }

    $nicknameSql = escapeSqlLiteral($nickname);

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $sqlCommand = "select t.* from usuarios as t where t.nickname = '$nicknameSql'";
    $usuario = $conn->Query($sqlCommand);

    if ($usuario === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($usuario) == 0) {
        $conn->Close();
        return getResultObject(false, 'Usuario o contraseña incorrectos');
    }

    $pwdHashed = hash("sha3-512", $pwd);

    if (hash_equals((string) $usuario[0]['pwd'], $pwdHashed)) {
        $sessionManager = new SessionManager();
        $session = $sessionManager->StartSession([], $usuario[0]);

        unset($session['user']['pwd']);

        $result = getResultObject(true, 'Sesión iniciada', $session);
    } else {
        $result = getResultObject(false, 'Usuario o contraseña incorrectos');
    }

    $conn->Close();

    return $result;
}

function logout($params, $token)
{
    $sessionManager = new SessionManager();
    $sessionManager->CloseSession($token);
    return getResultObject(true, 'Sesión finalizada');
}

function isLoggedIn($params, $token)
{
    $sessionManager = new SessionManager();
    $data['loggedIn'] = $sessionManager->isActive($token);
    return getResultObject(true, '', $data);
}

function changePassword($params, $token)
{
    $userId = getUserIdFromToken($token);
    if ($userId === null) {
        return getResultObject(false, 'Sesión inválida');
    }

    $actual = isset($params['actual']) ? (string) $params['actual'] : '';
    $nueva = isset($params['nueva']) ? (string) $params['nueva'] : '';
    $confirmacion = isset($params['confirmacion']) ? (string) $params['confirmacion'] : '';

    if ($actual === '' || $nueva === '' || $confirmacion === '') {
        return getResultObject(false, 'Debe indicar la clave actual y la nueva clave');
    }

    if ($nueva !== $confirmacion) {
        return getResultObject(false, 'La nueva clave y su confirmación no coinciden');
    }

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $sql = "SELECT id, pwd FROM usuarios WHERE id = $userId";
    $result = $conn->Query($sql);

    if ($result === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($result) === 0) {
        $conn->Close();
        return getResultObject(false, 'Usuario no encontrado');
    }

    $pwdActualHash = hash('sha3-512', $actual);
    if (!hash_equals((string) $result[0]['pwd'], $pwdActualHash)) {
        $conn->Close();
        return getResultObject(false, 'La clave actual es incorrecta');
    }

    $nuevaHash = hash('sha3-512', $nueva);
    $sqlUpdate = "UPDATE usuarios SET pwd = '$nuevaHash' WHERE id = $userId";
    $upd = $conn->Query($sqlUpdate);

    if ($upd === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $conn->Close();
    return getResultObject(true, 'Contraseña actualizada');
}

function createUsuario($params, $token)
{
    $userId = getUserIdFromToken($token);
    if ($userId === null) {
        return getResultObject(false, 'Sesión inválida');
    }

    $nickname = isset($params['nickname']) ? trim((string) $params['nickname']) : '';
    $nombre = isset($params['nombre']) ? trim((string) $params['nombre']) : '';
    $email = isset($params['email']) ? trim((string) $params['email']) : '';
    $pwd = isset($params['pwd']) ? (string) $params['pwd'] : '';
    $admin = isset($params['admin']) ? (int) $params['admin'] : 0;

    if ($nickname === '') {
        return getResultObject(false, 'Debe indicar el nombre de usuario');
    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $nickname)) {
        return getResultObject(false, 'Nombre de usuario inválido');
    }

    if ($pwd === '') {
        return getResultObject(false, 'Debe indicar la contraseña');
    }

    if (strlen($pwd) < 4) {
        return getResultObject(false, 'La contraseña debe tener al menos 4 caracteres');
    }

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $sqlAdmin = "SELECT admin FROM usuarios WHERE id = $userId";
    $resAdmin = $conn->Query($sqlAdmin);

    if ($resAdmin === false || count($resAdmin) === 0) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg !== '' ? $msg : 'No autorizado');
    }

    if ((int) $resAdmin[0]['admin'] !== 1) {
        $conn->Close();
        return getResultObject(false, 'No tiene permisos para crear usuarios');
    }

    $nicknameSql = escapeSqlLiteral($nickname);
    $nombreSql = escapeSqlLiteral($nombre);
    $emailSql = escapeSqlLiteral($email);
    $pwdHash = hash('sha3-512', $pwd);

    $sqlCheck = "SELECT id FROM usuarios WHERE nickname = '$nicknameSql' LIMIT 1";
    $resCheck = $conn->Query($sqlCheck);

    if ($resCheck === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($resCheck) > 0) {
        $conn->Close();
        return getResultObject(false, 'El nombre de usuario ya existe');
    }

    if (!$conn->BeginTransaction()) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $result = $conn->Query("SELECT t.id FROM usuarios AS t ORDER BY t.id DESC LIMIT 1");

    if ($result === false) {
        $conn->RollbackTransaction();
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $newId = 1;
    if (count($result) > 0) {
        $newId = (int) $result[0]['id'] + 1;
    }

    $sql = "INSERT INTO usuarios (id, nickname, nombre, email, pwd, admin) VALUES ($newId, '$nicknameSql', '$nombreSql', '$emailSql', '$pwdHash', $admin)";
    $res = $conn->Query($sql);

    if ($res === false) {
        $conn->RollbackTransaction();
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (!$conn->CommitTransaction()) {
        $conn->RollbackTransaction();
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $conn->Close();
    return getResultObject(true, 'Usuario creado', [
        'id' => $newId,
        'nickname' => $nickname,
        'nombre' => $nombre,
        'email' => $email,
        'admin' => $admin,
    ]);
}

function registerBiometric($params, $token)
{
    $userId = getUserIdFromToken($token);
    if ($userId === null) {
        return getResultObject(false, 'Sesión inválida');
    }

    $bioToken = isset($params['bioToken']) ? trim((string) $params['bioToken']) : '';
    if ($bioToken === '' || strlen($bioToken) < 16) {
        return getResultObject(false, 'Token biométrico inválido');
    }

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $bioTokenSql = escapeSqlLiteral($bioToken);
    $sql = "UPDATE usuarios SET bio_token = '$bioTokenSql' WHERE id = $userId";
    $result = $conn->Query($sql);

    if ($result === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $conn->Close();
    return getResultObject(true, 'Biometría registrada');
}

function loginBiometric($params)
{
    $nickname = isset($params['nickname']) ? trim((string) $params['nickname']) : '';
    $bioToken = isset($params['bioToken']) ? trim((string) $params['bioToken']) : '';

    if ($nickname === '' || $bioToken === '') {
        return getResultObject(false, 'Debe indicar usuario y credencial biométrica');
    }

    $nicknameSql = escapeSqlLiteral($nickname);
    $bioTokenSql = escapeSqlLiteral($bioToken);

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $sql = "SELECT * FROM usuarios WHERE nickname = '$nicknameSql' AND bio_token = '$bioTokenSql'";
    $usuario = $conn->Query($sql);

    if ($usuario === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($usuario) === 0) {
        $conn->Close();
        return getResultObject(false, 'Biometría no registrada o credencial inválida');
    }

    $sessionManager = new SessionManager();
    $session = $sessionManager->StartSession([], $usuario[0]);

    unset($session['user']['pwd']);
    unset($session['user']['bio_token']);

    $conn->Close();
    return getResultObject(true, 'Sesión iniciada', $session);
}

function disableBiometric($params, $token)
{
    $userId = getUserIdFromToken($token);
    if ($userId === null) {
        return getResultObject(false, 'Sesión inválida');
    }

    $dbInfo = getMySqlDbInfo(SAIVERAPP_DB);
    $conn = new MySqlDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $sql = "UPDATE usuarios SET bio_token = NULL WHERE id = $userId";
    $result = $conn->Query($sql);

    if ($result === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    $conn->Close();
    return getResultObject(true, 'Datos biométricos eliminados');
}

function saintProductosLoad($jsonParams)
{
    $id = isset($jsonParams['id']) ? $jsonParams['id'] : '';

    $dbInfo = getSqlDbInfo(SAINT_DB);
    $conn = new SqlServerDataManager($dbInfo);

    if (!$conn->IsConnected()) {
        return getResultObject(false, $conn->GetErrorMessage());
    }

    $codigoPesos = '1PC';
    $codigoDolar = '6USD';

    $sqlCommand =
        "select
            p.codprod as codprod,
            p.refere as codbarra,
            p.descrip as descrip,
            p.precio1 as precio1bs,
            p.precio2 as precio2bs,
            p.precio3 as precio3bs
        from
            saprod as p";

    if ($id != '') {
        $idSql = escapeSqlLiteral($id);
        $sqlCommand .= "
        where
            p.codprod = '$idSql' or
            p.refere = '$idSql' or
            charindex('$idSql', p.descrip) > 0";
    }

    $result = $conn->Query($sqlCommand);

    if ($result === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($result) == 0) {
        $conn->Close();
        return getResultObject(false, 'Producto no encontrado');
    }

    $sqlCommand =
        "select
            t.monto,
            t.tipotra,
            t.impuestod
        from
            satarj as t
        where
            t.codtarj = '$codigoPesos'";
    $resultCol = $conn->Query($sqlCommand);

    if ($resultCol === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($resultCol) == 0) {
        $tasaCol = 0;
        $tipoOper = 1;
        $tasaIGTF = 0;
    } else {
        $resultCol = $resultCol[0];
        $tasaCol = floatval($resultCol['monto']);
        $tipoOper = intval($resultCol['tipotra']);
        $tasaIGTF = floatval($resultCol['impuestod']);
    }

    for ($i = 0; $i < count($result); $i++) {
        switch ($tipoOper) {
            case 0:
                $precio1Col = floatval($result[$i]['precio1bs']) / $tasaCol;
                $igtf = ($precio1Col * $tasaIGTF) / 100;
                $precio1ColIGTF = $precio1Col + $igtf;

                $precio2Col = floatval($result[$i]['precio2bs']) / $tasaCol;
                $igtf = ($precio2Col * $tasaIGTF) / 100;
                $precio2ColIGTF = $precio2Col + $igtf;

                $precio3Col = floatval($result[$i]['precio3bs']) / $tasaCol;
                $igtf = ($precio3Col * $tasaIGTF) / 100;
                $precio3ColIGTF = $precio3Col + $igtf;
                break;
            case 1:
                $precio1Col = floatval($result[$i]['precio1bs']) * $tasaCol;
                $igtf = ($precio1Col * $tasaIGTF) / 100;
                $precio1ColIGTF = $precio1Col + $igtf;

                $precio2Col = floatval($result[$i]['precio2bs']) * $tasaCol;
                $igtf = ($precio2Col * $tasaIGTF) / 100;
                $precio2ColIGTF = $precio2Col + $igtf;

                $precio3Col = floatval($result[$i]['precio3bs']) * $tasaCol;
                $igtf = ($precio3Col * $tasaIGTF) / 100;
                $precio3ColIGTF = $precio3Col + $igtf;
                break;
            default:
                $precio1Col = 0;
                $precio1ColIGTF = 0;
                $precio2Col = 0;
                $precio2ColIGTF = 0;
                $precio3Col = 0;
                $precio3ColIGTF = 0;
                break;
        }
        $result[$i]['precio1col'] = $precio1Col;
        $result[$i]['precio1coligtf'] = $precio1ColIGTF;
        $result[$i]['precio2col'] = $precio2Col;
        $result[$i]['precio2coligtf'] = $precio2ColIGTF;
        $result[$i]['precio3col'] = $precio3Col;
        $result[$i]['precio3coligtf'] = $precio3ColIGTF;
    }

    $sqlCommand =
        "select
            t.monto,
            t.tipotra,
            t.impuestod
        from
            satarj as t
        where
            t.codtarj = '$codigoDolar'";
    $resultUsd = $conn->Query($sqlCommand);

    if ($resultUsd === false) {
        $msg = $conn->GetErrorMessage();
        $conn->Close();
        return getResultObject(false, $msg);
    }

    if (count($resultUsd) == 0) {
        $tasaUsd = 0;
        $tipoOper = 1;
        $tasaIGTF = 0;
    } else {
        $resultUsd = $resultUsd[0];
        $tasaUsd = floatval($resultUsd['monto']);
        $tipoOper = intval($resultUsd['tipotra']);
        $tasaIGTF = floatval($resultUsd['impuestod']);
    }

    for ($i = 0; $i < count($result); $i++) {
        switch ($tipoOper) {
            case 0:
                $precio1Usd = floatval($result[$i]['precio1bs']) / $tasaUsd;
                $igtf = ($precio1Usd * $tasaIGTF) / 100;
                $precio1UsdIGTF = $precio1Usd + $igtf;

                $precio2Usd = floatval($result[$i]['precio2bs']) / $tasaUsd;
                $igtf = ($precio2Usd * $tasaIGTF) / 100;
                $precio2UsdIGTF = $precio2Usd + $igtf;

                $precio3Usd = floatval($result[$i]['precio3bs']) / $tasaUsd;
                $igtf = ($precio3Usd * $tasaIGTF) / 100;
                $precio3UsdIGTF = $precio3Usd + $igtf;
                break;
            case 1:
                $precio1Usd = floatval($result[$i]['precio1bs']) * $tasaUsd;
                $igtf = ($precio1Usd * $tasaIGTF) / 100;
                $precio1UsdIGTF = $precio1Usd + $igtf;

                $precio2Usd = floatval($result[$i]['precio2bs']) * $tasaUsd;
                $igtf = ($precio2Usd * $tasaIGTF) / 100;
                $precio2UsdIGTF = $precio2Usd + $igtf;

                $precio3Usd = floatval($result[$i]['precio3bs']) * $tasaUsd;
                $igtf = ($precio3Usd * $tasaIGTF) / 100;
                $precio3UsdIGTF = $precio3Usd + $igtf;
                break;
            default:
                $precio1Usd = 0;
                $precio1UsdIGTF = 0;
                $precio2Usd = 0;
                $precio2UsdIGTF = 0;
                $precio3Usd = 0;
                $precio3UsdIGTF = 0;
                break;
        }
        $result[$i]['precio1usd'] = $precio1Usd;
        $result[$i]['precio1usdigtf'] = $precio1UsdIGTF;
        $result[$i]['precio2usd'] = $precio2Usd;
        $result[$i]['precio2usdigtf'] = $precio2UsdIGTF;
        $result[$i]['precio3usd'] = $precio3Usd;
        $result[$i]['precio3usdigtf'] = $precio3UsdIGTF;
    }

    $conn->Close();

    if ($id != '') {
        if (count($result) == 0) {
            return getResultObject(false, 'No se encontró el registro del producto');
        }
        $result = $result[0];
    }

    return getResultObject(true, '', $result);
}
