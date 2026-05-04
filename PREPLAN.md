# Architecture I am thinking

```
optiz/
├── src/
│   ├── Manager.php       # Main Controller (Singleton/Instance logic)
│   ├── Registry.php      # Data Store for Schema & Options
│   ├── Parser.php        # Schema validation & Default merging
│   ├── Renderer.php      # UI Generator (Tabs & Fields)
│   ├── Validator.php     # Sanitization & Custom Callbacks
│   └── Assets.php        # Conditional CSS/JS loading
├── assets/
│   ├── css/
│   │   └── optiz-admin.css
│   └── js/
│       ├── conditional.js # Dependency logic
│       └── media.js      # Optional media uploader logic
├── init.php              # The "Gatekeeper" (Election Logic)
└── composer.json         # Version & Autoload Config
```
