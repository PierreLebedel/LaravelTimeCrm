# TimeCRM

```bash
npm run dev;
npm run build;
php artisan queue:listen;
php artisan queue:work;
```

```bash
php artisan native:build win x64;
```

## Publier une release

Dans le fichier `.env.example` :
```env
NATIVEPHP_APP_VERSION=1.0.1
```

Sur GitHub, créer une release sur GitHub avec le tag v1.0.1 et l'enregistrer en **draft**

Avec GIT :
```bash
git commit -m "Release v1.0.1";
git tag v1.0.1;
git push origin main --tags;
```

## Annuler une release

```bash
git tag -d v1.0.0;
git push origin --delete v1.0.0;
```

