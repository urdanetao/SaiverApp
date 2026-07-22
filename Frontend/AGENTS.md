# AGENTS.md — SaiverApp

App de consultor de precios ("SaiverApp") en React 19 + Vite. Cliente frontend que se comunica con un backend PHP con doble base de datos (MySQL para usuarios, SQL Server para productos). Es una **app móvil híbrida** (WebView Android + frontend web).

## Repositorios / rutas del proyecto

- **Frontend (este repo):** `C:\source\react\smartsoft\SaiverApp` — código React/Vite.
- **Backend PHP:** `C:\xampp\htdocs\smartsoft\saiverapp` — API REST (`api.php` + `apicode.php` + `mysql-data-manager.php`). Corre en XAMPP local.
- **Código de la APK:** `C:\Users\Oscar\AndroidStudioProjects\SaiverApp` — proyecto Android (WebView) que embebe el frontend.
- **Base de datos (schema):** `C:\source\react\smartsoft\SaiverApp\database` — schema.sql + seed.sql (MySQL, solo usuarios).

## Referencia

- Proyecto MiCarrito (base para esta app): `C:\source\react\smartsoft\micarrito`
- Backend de referencia para lógica de precios: `C:\xampp\htdocs\smartsoft\saivernet` (función `saintProductosLoad` en `apicode.php`)
- SQL Server: tabla `saprod` (productos), tabla `satarj` (tarifas COP/USD)

## Comandos

- `npm run dev` — servidor de desarrollo (Vite).
- `npm run build` — build de producción (`vite build`).
- `npm run lint` — ESLint (`eslint .`). No hay typecheck ni tests configurados.
- `npm run preview` — sirve el build de producción.

No hay suite de tests. No ejecutar `npm test` (no existe).

## Arquitectura

- Entrada: `src/main.jsx` monta `<App>` dentro de `<ToastProvider>`. `src/App.jsx` alterna entre `Login` y `Engine` según la sesión en `sessionStorage`.
- Directorios de `src/`:
  - `components/` — UI reutilizable (`Core*`). Se importan vía el barrel `components/index.js`.
  - `system/` — pantallas/módulos de negocio (`Login`, `Engine`, `Consultor`, `Configuracion`).
  - `hooks/` — `useLazyFetch` (fetch centralizado a la API) y `Toast`.
  - `util/` — `util.js` (sesión, back handler), `constants.js`.

## Backend / API

- Todas las llamadas pasan por `useLazyFetch` → `fetchData(action, params)`. POST JSON a `VITE_API_URL` (default `/api.php`) con `{ action, params, token }`.
- `base: './'` en Vite: rutas relativas en el build.
- Doble base de datos:
  - **MySQL** (`smartsoft_saiverapp`): tabla `usuarios` (auth, login, pwd sha3-512, admin, bio_token).
  - **SQL Server** (`saiverdb`): `saprod` (productos con 3 niveles de precio en Bs) + `satarj` (tarifas de conversión COP/USD).
- `saintProductosLoad` busca por código, código de barras o descripción, y retorna precios en Bs/USD/COP con y sin IGTF.

## Convenciones

- Mismas que MiCarrito (estilos inline, react-icons, Core* components, backHandlerRegistry).
- `showConfirm` recibe un **objeto** `{ text, okAction }`.
- La app usa `history.pushState` para navegación (no router).
- Botón Atrás de Android: siempre cierra modal/confirm primero.
