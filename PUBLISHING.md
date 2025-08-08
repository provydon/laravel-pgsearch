# Publishing Checklist

## Pre-Publishing Steps

### ✅ Code Quality
- [x] All tests passing (`composer test`)
- [x] Code formatted with Pint (`composer format`)
- [x] No linting errors
- [x] README documentation complete
- [x] License file present

### ✅ Package Configuration
- [x] `composer.json` properly configured
- [x] Service provider auto-discovery setup
- [x] Config file publishable
- [x] Proper namespacing

## GitHub Repository Setup

### 1. Create Repository
- **Name:** `laravel-pgsearch`
- **Description:** `Laravel package for PostgreSQL full-text search with ILIKE queries and text normalization`
- **Visibility:** Public
- **License:** MIT

### 2. Repository Settings
```bash
# Add remote origin
git remote add origin https://github.com/YOUR_USERNAME/laravel-pgsearch.git

# Push to GitHub
git add .
git commit -m "Initial release: Laravel PostgreSQL search package"
git branch -M main
git push -u origin main
```

### 3. Add Topics/Tags
Go to your GitHub repo settings and add these topics:
- `laravel`
- `postgresql`
- `search`
- `ilike`
- `full-text-search`
- `eloquent`
- `normalization`
- `php`

## Packagist Registration

### 1. Submit to Packagist
1. Go to [packagist.org](https://packagist.org)
2. Sign in with GitHub
3. Click "Submit" 
4. Enter your repository URL: `https://github.com/YOUR_USERNAME/laravel-pgsearch`
5. Click "Check" then "Submit"

### 2. Auto-Update Setup
1. In your GitHub repo, go to Settings → Webhooks
2. Add webhook URL from Packagist
3. Set content type to `application/json`
4. Select "Just the push event"

## Version Tagging

### Create First Release
```bash
# Tag version 1.0.0
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```

### GitHub Release
1. Go to GitHub repo → Releases
2. Click "Create a new release"
3. Choose tag `v1.0.0`
4. Title: `v1.0.0 - Initial Release`
5. Description: Feature list and installation instructions

## Post-Publishing

### 1. Installation Test
```bash
# Test installation in a fresh Laravel project
composer require provydon/laravel-pgsearch
```

### 2. Documentation
- [ ] Update README with installation badge
- [ ] Add changelog file
- [ ] Consider creating documentation site

### 3. Community
- [ ] Share on Laravel News
- [ ] Post on Reddit r/laravel
- [ ] Tweet about the package
- [ ] Add to awesome-laravel lists

## Maintenance

### Future Updates
1. Make changes
2. Update version in `composer.json` (optional)
3. Run tests: `composer test`
4. Format code: `composer format`
5. Commit changes
6. Tag new version: `git tag -a v1.0.1 -m "Bug fixes"`
7. Push: `git push && git push --tags`
8. Create GitHub release

### Semantic Versioning
- **MAJOR** (1.0.0): Breaking changes
- **MINOR** (1.1.0): New features, backward compatible
- **PATCH** (1.0.1): Bug fixes, backward compatible
