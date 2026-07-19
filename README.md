
![Logo](https://github.com/wereunivocal/cellula/blob/main/screenshot.png?raw=true)


# Cellula
*Cellula: \[italian noun\] /'tʃɛlːula/ (biology) a very small piece of the substance of which all living things are made; the smallest unit of living matter* 

A Wordpress classic theme in less than 4kb single-line php. A proof of concept for the smallest, yet fully featured wordpress theme. For more informations, see this [blog post](https://univocal.co/cellula-wordpress-theme/).

If you find this theme useful, a donation is genuinely appreciated. You can buy me a coffee [here](https://buymeacoffee.com/univocal).

<a href="https://www.buymeacoffee.com/univocal" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me a Coffee" style="height: 60px !important;width: 217px !important;" ></a>

*AI Disclosure*: Cloude Code has been used exclusively for debugging the `minify.php` script. The main *Cellula* theme has been entirely written and debugged by hand. 

## Requirements
- WordPress >= 6.9
- PHP >= 8.0
- [Composer](https://getcomposer.org)

## Features

- Comments;
- Sidebar Widgets;
- Full support for embed and media.

## How to build Cellula

Simply invoke `composer minify` to generate the minified `index.php` file.

## Changelog
### [1.1.0] - 2026-07-19

#### Improved
- Improved styling and layout
- Smaller output size - less than 3 Kbytes for the final `index.php`!

#### Fixed
- Replace `is_single` with `is_singular` so pages work by [@jakeparis](https://github.com/jakeparis)

## License

[GPL2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

