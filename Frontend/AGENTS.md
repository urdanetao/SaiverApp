# AGENTS.md — SaiverApp

App de consultor de precios ("SaiverApp"). App movil hibrida (WebView Android + frontend web) con React 19 + Vite y backend PHP con doble base de datos (MySQL para usuarios, SQL Server para productos).

## Repositorio

- **GitHub:** https://github.com/urdanetao/SaiverApp
- **Estructura del repo:**
  ```
  SaiverApp/
  ├── Frontend/       React 19 + Vite (SPA)
  ├── Backend/        PHP API (MySQL + SQL Server)
  ├── Android/        WebView wrapper nativo (Kotlin)
  └── Database/       Schema y seed MySQL
  ```

## Rutas locales

- **Frontend:** `C:\source\react\smartsoft\saiverapp\Frontend`
- **Backend PHP:** `C:\xampp\htdocs\smartsoft\saiverapp` (tambien en `Backend/` del repo)
- **Android:** `C:\Users\Oscar\AndroidStudioProjects\SaiverApp` (tambien en `Android/` del repo)

## Referencia

- Proyecto MiCarrito (base para esta app): `C:\source\react\smartsoft\micarrito`
- Backend de referencia: `C:\xampp\htdocs\smartsoft\saivernet` (funcion `saintProductosLoad` en `apicode.php`)
- SQL Server: tabla `saprod` (productos), tabla `satarj` (tarifas COP/USD)

## Comandos

**Frontend** (ejecutar desde `Frontend/`):
- `npm install` — instalar dependencias.
- `npm run dev` — servidor de desarrollo (Vite).
- `npm run build` — build de produccion (`vite build`).
- `npm run lint` — ESLint. No hay typecheck ni tests configurados.

**Android:** Abrir `Android/` en Android Studio, sincronizar Gradle, compilar.

No hay suite de tests. No ejecutar `npm test` (no existe).

## Arquitectura

### Frontend
- Entrada: `src/main.jsx` monta `<App>` dentro de `<ToastProvider>`. `src/App.jsx` alterna entre `Login` y `Engine` segun la sesion en `sessionStorage`.
- Directorios de `src/`:
  - `components/` — UI reutilizable (`Core*`, `BarcodeScanner`). Se importan via el barrel `components/index.js`.
  - `system/` — pantallas/modulos de negocio (`Login`, `Engine`, `Consultor`, `Configuracion`).
  - `hooks/` — `useLazyFetch` (fetch centralizado a la API) y `Toast`.
  - `util/` — `util.js` (sesion, back handler, `isRunningInWebView`), `constants.js` (`ENTRY_MODE`, `COLOR_MAP`).

### Backend PHP
- Todas las llamadas pasan por `useLazyFetch` -> `fetchData(action, params)`. POST JSON a `./php/api.php` con `{ action, params, token }`.
- Doble base de datos:
  - **MySQL** (`smartsoft_saiverapp`): tabla `usuarios` (auth, login, pwd sha3-512, admin, bio_token).
  - **SQL Server** (`saiverdb`): `saprod` (productos con 3 niveles de precio en Bs) + `satarj` (tarifas de conversion COP/USD).
- `saintProductosLoad` busca por codigo, codigo de barras o descripcion, y retorna precios en Bs/USD/COP con y sin IGTF.
- `dbinfo.php` NO se sube al repo. Usar `dbinfo.php.example` como template. Se configuran variables de entorno o se editan los defaults.
- CORS: `api.php` tiene whitelist de origenes. En el repo, las IPs locales estan como `TU_IP_LOCAL`.
- **Nota:** En XAMPP (`C:\xampp\htdocs\smartsoft\saiverapp`) hay una copia del Backend con la IP local real (`192.168.1.20`). Para evitar duplicacion, se puede usar un symlink: `mklink /D "C:\xampp\htdocs\smartsoft\saiverapp" "C:\source\react\smartsoft\saiverapp\Backend"`.

### Android
- `MainActivity.kt`: WebView que carga la URL de produccion (`https://almacenadorasaiver.com/saiverapp`). `BASE_URL` se puede cambiar para dev.
- `BarcodeScannerActivity.kt`: Scanner nativo con CameraX + ML Kit. Retorna el barcode via `onActivityResult` -> `window.onBarcodeScanned()`.
- `BiometricHelper.kt`: Autenticacion biometrica con AndroidX Biometric.
- El WebView expone `Android.scanBarcode()` y `Android.showToast()` al JavaScript.
- `local.properties` NO se sube al repo.

## Consultor de Precios

- Al abrir se precargan TODOS los productos en el `CoreSuggest` (via `saintProductosLoad` con `id: ''`).
- El `CoreSuggest` filtra localmente por codigo, codigo de barras o descripcion.
- La lista desplegable muestra solo la descripcion (`listDisplayField="text1"`), no el codigo.
- Ancho de la lista: `listDisplayWidth="calc(100% + 44px)"` para llegar al borde del boton de escanear.
- Al seleccionar del dropdown o presionar Enter, se busca via API y se muestra el producto.
- Al escanear barcode:
  - En Android: `Android.scanBarcode()` abre `BarcodeScannerActivity` (CameraX + ML Kit).
  - En navegador: se usa `BarcodeScanner` (html5-qrcode) como fallback.
  - El codigo escaneado se convierte aUpperCase antes de buscar (consistencia con `ENTRY_MODE.UPPER`).
  - El codigo escaneado se envia a `saintProductosLoad` que busca en `codprod`, `refere` (codbarra) o `descrip`.
- Fix race condition: `CoreSuggest.skipNextBlurCommit()` evita que el `handleBlur` sobreescriba el resultado de la API despues de un scan. Se usa via `suggestRef` en `handleSearch`.
- `handleSelectProduct` limpia el error (`setError('')`) para que no persista al seleccionar del dropdown.
- Despues de mostrar resultados, el `CoreSuggest` queda en blanco y la info del producto persiste.
- `ENTRY_MODE.UPPER` para el CoreSuggest.

## CoreSuggest — Props y metodos

- `listDisplayField`: campo para mostrar en la lista desplegable (por defecto usa `displayField`).
- `listDisplayWidth`: ancho de la lista desplegable (por defecto igual al `width` del input).
- `ref.skipNextBlurCommit()`: metodo expuesto via `useImperativeHandle` para evitar que `handleBlur` haga commit del valor del input. Usar antes de `blur()` cuando se busca via API (scan/enter) para evitar race conditions.

## Convenciones

- Mismas que MiCarrito (estilos inline, react-icons, Core* components, backHandlerRegistry).
- `showConfirm` recibe un **objeto** `{ text, okAction }`.
- La app usa `history.pushState` para navegacion (no router).
- Boton Atras de Android: siempre cierra modal/confirm primero.
- Admin check: `sessionData?.user?.admin === '1'` (string comparison).
- Sesion almacenada en `sessionStorage` bajo la key `sessionData`.
- Produccion: `https://almacenadorasaiver.com/saiverapp/`

## Despliegue

- Frontend se compila con `npm run build` (desde `Frontend/`).
- El build resultante (`Frontend/dist/`) se sube manualmente al servidor de produccion.
- No hay CI/CD configurado.
- **IMPORTANTE:** No ejecutar `npm run build` desde la raiz del proyecto (crea `dist/` y `node_modules/` en la raíz por error).
