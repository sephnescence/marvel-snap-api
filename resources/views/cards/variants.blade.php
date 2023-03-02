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
            @foreach ($cards as $card)
                @foreach ($card->variants as $variant)
                    <div class="generated-card">
                        <div class="generated-card-inner">
                            <!-- I'm not sure how to generate this url properly... -->
                            <div
                                class="generated-card-background-one"
                                style="background-image: url('http://localhost:5173/resources/images/variants/{{$variant->name}}/Background1.webp');"
                            >
                                <div
                                    class="generated-card-background-two"
                                    style="background-image: url('http://localhost:5173/resources/images/variants/{{$variant->name}}/Background2.webp');"
                                >
                                </div>
                            </div>
                            <div
                                class="generated-card-foreground"
                                style="background-image: url('http://localhost:5173/resources/images/variants/{{$variant->name}}/Foreground1.webp');"
                            >
                                <div
                                    class="generated-card-logo"
                                    style="background-image: url('http://localhost:5173/resources/images/variants/{{$variant->name}}/Logo.webp');"
                                >
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </body>
</html>