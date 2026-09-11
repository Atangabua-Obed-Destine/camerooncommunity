# PWA icon source

Drop your app logo here as **`icon-source.png`**:

- square (1024×1024 or larger)
- transparent background preferred — `pwa:icons` adds the brand blue behind the
  maskable and iOS icons itself

Then regenerate every icon and the favicon:

```bash
php artisan pwa:icons
```

Until that file exists, the command falls back to `public/images/cameroonflag.png`
so the PWA is installable and testable with a placeholder icon.

Generated output (committed): `public/icons/*.png` and `public/favicon.ico`.
