# Family Tree Connect

A comprehensive WordPress plugin for creating and managing family trees with advanced features.

## Features

- **Person Management**: Full biographical data including names (maiden name, nickname), dates with multiple calendar support
- **Family Tracking**: Marriage/partnership records with dates, locations, and children management
- **Relationship Calculator**: BFS-based pathfinding with human-readable relationship names
- **Multi-Calendar Support**: Gregorian (default), Jewish (Hebrew), Julian, Islamic with automatic conversions
- **Media System**: Photo uploads, non-destructive cropping, event linking
- **Facial Recognition**: Client-side face detection with matching suggestions using face-api.js
- **Chart Generation**: Ancestor/descendant/hourglass/family charts in multiple orientations
- **Profile Merging**: Request/approval workflow for connecting profiles across users
- **GEDCOM Support**: Import and export using the standard genealogy format
- **RTL Support**: Full Hebrew/Arabic language compatibility
- **Search**: By name (including maiden names), places, with autocomplete

## Installation

1. Upload the `family-tree-connect` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Go to Family Tree menu to create your first tree

## Shortcodes

- `[ftc_tree id="1"]` - Display a family tree
- `[ftc_person id="1"]` or `[ftc_person uuid="xxx"]` - Display a person profile
- `[ftc_chart person_id="1" type="ancestor" generations="4"]` - Display a chart
- `[ftc_search]` - Display a search form
- `[ftc_dashboard]` - Display user dashboard
- `[ftc_index tree_id="1"]` - Display alphabetical person index
- `[ftc_place id="1"]` - Display place with associated people

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+

## License

GPL v2 or later
