I moved to a new Mac after initialising this. Naturally that means my vendor folder didn't follow me as it's in the gitignore. To get around this I ended up doing the following
1. Install Brew - https://brew.sh/
  1. Be sure to follow the special notes section. In this case it gave me two lines to run in my terminal to add brew to my path
1. Close that terminal window and open another one
1. Make sure Brew is up to date `brew update`
1. Then run `brew install php`
  1. It offered to run php on startup. I'm not sure if I need to but I'll leave it here for reference - `brew services start php`
1. 
1. Then install composer. Instructions at https://getcomposer.org/download/
  1. `php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"`
  1. `php -r "if (hash_file('sha384', 'composer-setup.php') === '55ce33d7678c5a611085589f1f3ddf8b3c52d662cd01d4ba75c0ee0459970c2200a51f492d557530c71c15d8dba01eae') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"`
  1. `php composer-setup.php`
  1. `sudo mv composer.phar /usr/local/bin/composer`
1. Close that terminal window and open another one
1. `composer install`

And after all that I don't have a .env file lol. So I give up. I'm just going to regenerate the app. Turns out I had to anyways as I'm not on an M2 and packages changed. Like selenium to seleniarm

I finally tracked this down in the docs though... Not sure if `--ignore-platform-reqs` would have meant that it would have installed intel mac stuff instead of silicon 
🤷

<hr />

I want to install something that allows for tstzranges, but I get "Your requirements could not be resolved to an installable set of packages." when trying to run `./vendor/bin/sail composer require umbrellio/laravel-pg-extensions`. https://packagist.org/packages/umbrellio/laravel-pg-extensions doesn't list Laravel 10 at the time. So I guess I'll have to live without it for now

```
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

<br />
<hr />

It's time to do some tests! I'm getting sick of manually have to test stuff lol. I guess I got too caught up in doing the thing, but in the end it's actually slower to move because things break and I have to fix them in addition to making sure the new stuff works too. This is literally what testing is for :D

Apparently Laravel 10 does both phpunit and pest tests, so that's nice. Though it also does phpunit in parallel out of the box. Neat!

`php artisan test --coverage` is handy by itself, but `php artisan test --coverage --min=80.3` can possibly be done as part of a pipeline if I care to. Probably not. I ran this without a min flag and there are actually files as part of Laravel itself that come back not tested at all. So I could probably look at removing them entirely later, but I'm not sure if I'll need them is all. I wonder if there's an ignore list as well 🤷

`php artisan test --profile` is handy to see what the slowest tests are. At the time of writing this, `ExampleTest` is the slowest one at 0.22s. The dream. I wish the could all be that fast haha

Full docs can be found here - https://laravel.com/docs/10.x/testing

When it comes to testing, it sounds like it's best to run `php artisan test --parallel --recreate-databases` as the testing database will not persist. This seems like the safest thing to do. That said, the parallel nature of it means I'm stuck with phpunit by the sounds of it and not pest. Not too sure on that though

You can tell the test suite how many processes you want with `php artisan test --parallel --processes=4` for example

I don't think I'll ever care to run unit tests outside of testing specific php things, so it's possible to skip them entirely with `php artisan test --testsuite=Feature`

You may create pest tests with an example like this. At the time of writing though, I'm not sure how to run pest tests. Even using the commands they suggested, I get `Call to undefined function test()`
```
php artisan make:test UserTest --pest
php artisan make:test UserTest --unit --pest
```

<br />
<hr />

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains over 2000 video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the Laravel [Patreon page](https://patreon.com/taylorotwell).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Cubet Techno Labs](https://cubettech.com)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[Many](https://www.many.co.uk)**
- **[Webdock, Fast VPS Hosting](https://www.webdock.io/en)**
- **[DevSquad](https://devsquad.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[OP.GG](https://op.gg)**
- **[WebReinvent](https://webreinvent.com/?utm_source=laravel&utm_medium=github&utm_campaign=patreon-sponsors)**
- **[Lendio](https://lendio.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
