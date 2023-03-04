<div class="generated-card">
    <div class="generated-card-inner">
        <div
            class="generated-card-background-one"
            @if (
                !empty($variant->internal_data['downloads']['backgrounds'])
                && in_array('Background1.webp', $variant->internal_data['downloads']['backgrounds'])
            )
                style="background-image: url({{ Vite::asset('resources/images/variants/' . $variant->name . '/Background1.webp') }});"
            @endif
        >
            <div
                class="generated-card-background-two"
                @if (
                    !empty($variant->internal_data['downloads']['backgrounds'])
                    && in_array('Background2.webp', $variant->internal_data['downloads']['backgrounds'])
                )
                    style="background-image: url({{ Vite::asset('resources/images/variants/' . $variant->name . '/Background2.webp') }});"
                @endif
            >
            </div>
        </div>
        <div
            class="generated-card-foreground"
            @if (
                !empty($variant->internal_data['downloads']['foregrounds'])
                && in_array('Foreground1.webp', $variant->internal_data['downloads']['foregrounds'])
            )
                style="background-image: url({{ Vite::asset('resources/images/variants/' . $variant->name . '/Foreground1.webp') }});"
            @endif
        >
            <div
                class="generated-card-logo"
                @php
                    echo 'style="background-image: url(' . Vite::asset('resources/images/variants/' . $variant->name . '/Logo.webp') . ');"'
                @endphp
            >
            </div>
        </div>
    </div>
</div>