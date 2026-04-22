# TimeCRM

```bash
npm run dev;
npm run build;
php artisan queue:listen;
php artisan queue:work;
```

```bash
php artisan native:build win;
```

## Publier une release

Dans le fichier `.env` :
```env
NATIVEPHP_APP_VERSION=1.0.1
```

Sur GitHub :
```bash
git commit -m "Release v1.0.1"
git tag v1.0.1
git push origin main --tags
```