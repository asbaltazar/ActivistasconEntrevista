# Reglas del proyecto — Red Ciudadana de Talentos

- La fuente de verdad funcional es `docs/PRD.md`. Consultarla antes de implementar.
- Stack: PHP 8.2 + CodeIgniter 4 (+ Shield), **MariaDB 10.5** (no usar funciones exclusivas de MySQL 8; coordenadas en lat/lng DECIMAL), Vue 3 + Vite + Pinia, PrimeVue 4 + Tailwind 4, Twilio (SMS + Verify).
- Monorepo: `/backend`, `/app-activista`, `/panel`, `/docs`.
- Idioma de UI y mensajes: español (México). Código e identificadores en inglés salvo campos de dominio INE.
- NUNCA escribir llaves de API, contraseñas ni secretos en el código; usar `.env` (backend) y `VITE_*` en `.env.local` (frontend, solo llave de navegador restringida).
- Las imágenes de INE/selfie se guardan fuera de `public_html` y se sirven solo autenticadas.
- Toda alta de talento es idempotente por `uuid` generado en cliente.
- La PWA debe funcionar offline: no asumir conectividad; toda captura se guarda primero en IndexedDB (Dexie).
- Usar migraciones y seeders de CI4 para cualquier cambio de BD.
- PHP: PSR-12, PHPStan nivel 6, PHPUnit. JS: ESLint + Prettier, Vitest, Playwright.
- Commits con Conventional Commits.
