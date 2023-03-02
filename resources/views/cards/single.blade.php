<html>
    <head>
        <meta charset="UTF-8" />
        <link rel="icon" type="image/svg+xml" href="/favicon.ico" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Laravel</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div>
            @foreach ($card->variants as $variant)
                @include('cards.variant', ['variant' => $variant])
            @endforeach
        </div>
    </body>
</html>