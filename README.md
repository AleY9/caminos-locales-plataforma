# Caminos Locales Plataforma

Plugin de WordPress de caminoslocales.com: panel del emprendedor, fichas y funciones propias del sitio. Detalles en `readme.txt`.

## Publicar una versión nueva

1. Subir los cambios con el número nuevo en `caminos-locales-plataforma.php` (en `Version:` y en `CLP_VERSION`) y una línea en «Cambios» de `readme.txt`.
2. Releases → **Draft a new release** → etiqueta `vX.Y.Z` (el mismo número) → **Publish release**.
3. GitHub arma solo `caminos-locales-plataforma.zip` y lo adjunta (pestaña Actions, ~1 min). El sitio ve la actualización en unas horas, o al instante con Escritorio → Actualizaciones → «Volver a comprobar».

Para instalar a mano, usar ese `caminos-locales-plataforma.zip` de la release, nunca «Source code».

## Cómo se trabaja

- Los cambios se prueban primero en `staging.caminoslocales.com`. El staging nunca se pasa a producción.
- Actualizar el plugin por código lo deja desactivado: volver a activarlo enseguida.
