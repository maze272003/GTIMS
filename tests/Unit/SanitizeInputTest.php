<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\SanitizeInput;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SanitizeInputTest extends TestCase
{
    protected SanitizeInput $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SanitizeInput();
    }

    public function test_strips_html_tags_from_string_input(): void
    {
        $request = Request::create('/test', 'POST', [
            'name' => '<script>alert("xss")</script>John',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('alert("xss")John', $req->input('name'));
            return new Response();
        });
    }

    public function test_does_not_strip_password_fields(): void
    {
        $request = Request::create('/test', 'POST', [
            'password' => '<strong>securepass</strong>',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('<strong>securepass</strong>', $req->input('password'));
            return new Response();
        });
    }

    public function test_sanitizes_nested_arrays(): void
    {
        $request = Request::create('/test', 'POST', [
            'items' => [
                ['name' => '<b>Bold</b> Name'],
                ['name' => '<em>Italic</em> Name'],
            ],
        ]);

        $this->middleware->handle($request, function ($req) {
            $items = $req->input('items');
            $this->assertEquals('Bold Name', $items[0]['name']);
            $this->assertEquals('Italic Name', $items[1]['name']);
            return new Response();
        });
    }

    public function test_leaves_clean_input_unchanged(): void
    {
        $request = Request::create('/test', 'POST', [
            'name' => 'Clean Text',
            'count' => 42,
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('Clean Text', $req->input('name'));
            $this->assertEquals(42, $req->input('count'));
            return new Response();
        });
    }
}
