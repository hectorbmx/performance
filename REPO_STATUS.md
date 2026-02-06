# Repo Status (Fuente de la verdad)

## Situación actual
Este repo contiene código mezclado (Laravel + Ionic) por error histórico.

## Fuente de la verdad HOY
- MOBILE (Ionic/Capacitor): rama `master` en `origin` (repo: performance.git)
- Backend (Laravel): si aplica, usar rama `main` (confirmar cuando se retome backend)

## Remotes
- origin: https://github.com/hectorbmx/performance.git  ✅ (verdad temporal)
- mobile: https://github.com/hectorbmx/performanceApp.git (desfasado / push pendiente por conectividad)

## Reglas para no volver a romperlo
1. **Solo hacer cambios mobile en `master`**
2. **No commitear nada de Laravel en `master`**
3. Antes de push:
   - `git status`
   - `git branch --show-current`
   - `git remote -v`

## Nota
Cuando haya conectividad, sincronizar `master` hacia el repo solo mobile (`performanceApp.git`).
Comando esperado:
`git push mobile master:master`
