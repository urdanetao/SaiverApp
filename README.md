# SaiverApp

Aplicación móvil híbrida (React + PHP + Android WebView) para consulta de precios de productos.

## Estructura

```
├── Frontend/       React 19 + Vite (SPA)
├── Backend/        PHP API (MySQL + SQL Server)
├── Android/        WebView wrapper nativo (Kotlin)
├── Database/       Schema y seed MySQL
```

## Requisitos

- Node.js 18+
- PHP 8.1+ (XAMPP o similar)
- MySQL 8.0+
- SQL Server (para datos de productos)
- Android Studio (para compilar el APK)

## Configuración

### Backend

1. Copiar `Backend/dbinfo.php.example` a `Backend/dbinfo.php`
2. Editar `dbinfo.php` con tus credenciales de MySQL y SQL Server
3. Ejecutar `Database/schema.sql` en MySQL para crear la base de datos
4. Ejecutar `Database/seed.sql` para crear el usuario admin por defecto
5. En `api.php`, reemplazar `TU_IP_LOCAL` por la IP de tu máquina en la red local

### Frontend

```bash
cd Frontend
npm install
npm run dev
```

### Android

1. Abrir `Android/` en Android Studio
2. Sincronizar Gradle
3. Compilar y ejecutar

## Producción

- Frontend: `https://almacenadorasaiver.com/saiverapp/`
- Backend: `https://almacenadorasaiver.com/saiverapp/php/api.php`
