<?php

namespace Tests\Feature;

use App\Models\MarvelSnapCardSeries;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MarvelSnapCardSeriesTest extends TestCase
{
    private string $series1Id = '';

    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2023-01-01 00:00:00');
    }

    public function testExample(): void
    {
        $series1Model = MarvelSnapCardSeries::find($this->series1Id);
        dd($series1Model);

        $response = $this->get('/');

        $series = new MarvelSnapCardSeries;
        $series->id = $this->series1Id;
        $series->name = 'Test Series 1';
        $series->lifespan_start = Carbon::now();
        $series->lifespan_end = Carbon::maxValue();
        $series->created_at = Carbon::now();
        $series->updated_at = Carbon::now();
        $series->deleted_at = null;
        $series->save();

        $response->assertStatus(200);
    }
}
