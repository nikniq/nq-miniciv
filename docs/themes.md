# Themes

The application supports multiple themes for different frontpage layouts and page-specific designs. Themes control the appearance and functionality of various parts of the application.

## Frontpage Themes

The frontpage theme determines the homepage layout. Set via `FRONTPAGE_THEME` in `.env`.

### Available Frontpage Themes

#### Default Theme (`default`)

- Standard homepage with feature overview
- Links to login, dashboard
- Clean, professional design

**Configuration:**
```env
FRONTPAGE_THEME=default
```

#### Games Theme (`games`)

- Showcases available games
- Displays products in game category
- Includes favorites for logged-in users
- Interactive game listings

**Configuration:**
```env
FRONTPAGE_THEME=games
GAMES_CATEGORY=Game
APP_GAMES_ENABLED=true
```

#### License Theme (`license`)

- Focused on license validation
- Public license checker
- Minimal design for license management

**Configuration:**
```env
FRONTPAGE_THEME=license
APP_LICENSES_PUBLIC_VALIDATION=true
```

#### Login Theme (`login`)

- Prominent login form
- Registration links
- Simple authentication-focused design

**Configuration:**
```env
FRONTPAGE_THEME=login
```

## Page-Specific Themes

Some pages use dedicated themes:

### MiniCiv Theme (`miniciv`)

- Game interface for MiniCiv
- Interactive game elements
- SVG icons and game UI

### Certificate Theme (`cert`)

- Certificate management interface
- SSL certificate tools

### Whois Theme (`whois`)

- Domain whois lookup interface

### Subdomains Theme (`subdomains`)

- Subdomain management tools

### Logs Theme (`logs`)

- Log viewing interface

## Custom Themes

To create a custom theme:

1. Create a new folder in `resources/views/themes/your-theme/`
2. Add `frontpage.blade.php` for frontpage themes
3. Include necessary CSS/JS in the layout
4. Set `FRONTPAGE_THEME=your-theme` in `.env`

## Theme Structure

Each theme folder should contain:

```
themes/
  your-theme/
    frontpage.blade.php  # Main page template
    partials/            # Reusable components
    assets/              # Theme-specific assets
```

## Layout Integration

Themes extend the main `layouts.app` and can override sections:

```blade
@extends('layouts.app')

@section('content')
  <!-- Theme content -->
@endsection

@push('head')
  <!-- Theme styles -->
@endpush
```

## Configuration

### Environment Variables

- `FRONTPAGE_THEME`: Active frontpage theme (default, games, license, login)
- `GAMES_CATEGORY`: Product category for games theme
- `APP_GAMES_ENABLED`: Enable games features (default: false)
- `APP_LICENSES_PUBLIC_VALIDATION`: Enable license validation on license theme

### Theme-Specific Config

Some themes have additional config files:

- `config/games.php`: Games theme settings
- `config/frontpage.php`: Frontpage theme config

## Switching Themes

To switch themes:

1. Update `.env`: `FRONTPAGE_THEME=new-theme`
2. Clear config cache: `php artisan config:clear`
3. Restart application if needed

## Development

When developing themes:

- Use Tailwind CSS classes for styling
- Follow the existing theme structure
- Test on different screen sizes
- Ensure accessibility compliance

## Troubleshooting

- Verify theme folder exists in `resources/views/themes/`
- Check `.env` for correct `FRONTPAGE_THEME` value
- Clear Laravel caches: `php artisan view:clear`
- Check browser console for asset loading errors