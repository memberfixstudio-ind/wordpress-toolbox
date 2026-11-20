# WordPress Toolbox

**Small, focused WordPress plugins, one new one each week.**

Each folder here is a complete plugin you can zip and upload. They follow the same rules as my other plugins: they work the moment they're activated, keep settings to a minimum and look at home in wp-admin.

## Plugins

| Plugin | What it does | Added |
| --- | --- | --- |
| [**Notice Tray**](notice-tray/) | Moves plugin and theme notices off the top of every admin screen into a tray in the admin bar, with a count. Errors stay visible. | 2026-11-14 |

New plugins are added to the top of this table.

## Install a plugin

1. Download this repository as a ZIP and unzip it.
2. Zip the folder of the plugin you want, for example `plugin-name/`.
3. Upload it in **Plugins → Add New → Upload Plugin** and activate it.

## Checks

A GitHub Actions workflow lints every PHP file on PHP 7.4, 8.1 and 8.3 and syntax-checks every JavaScript file, so a plugin that declares PHP 7.4 support can't quietly use newer syntax.

## License

GPL-2.0-or-later. Built by [Rohan Salave](https://www.linkedin.com/in/rohan-salave-362475189/).
