<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Provydon\PgSearch\PgSearchServiceProvider;

class PgSearchTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [PgSearchServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($t) {
                $t->id();
                $t->string('name');
                $t->string('email')->nullable();
                $t->string('phone')->nullable();
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function ($t) {
                $t->id();
                $t->string('title');
                $t->text('content');
                $t->unsignedBigInteger('user_id');
                $t->foreign('user_id')->references('id')->on('users');
            });
        }

        Post::query()->delete();
        User::query()->delete();

        User::query()->insert([
            ['id' => 1, 'name' => 'Jane-Doe', 'email' => 'jane@example.com', 'phone' => '123-456-7890'],
            ['id' => 2, 'name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '987.654.3210'],
            ['id' => 3, 'name' => 'Bob Smith', 'email' => 'bob@test.com', 'phone' => '555-999-8888'],
            ['id' => 4, 'name' => 'Oak-Li', 'email' => 'oak@example.com', 'phone' => '111-222-3333'],
        ]);

        Post::query()->insert([
            ['title' => 'Laravel Tips', 'content' => 'Great framework', 'user_id' => 1],
            ['title' => 'PHP Best Practices', 'content' => 'Clean code matters', 'user_id' => 2],
            ['title' => 'Database Design', 'content' => 'Normalization is key', 'user_id' => 3],
            ['title' => 'Oak Development', 'content' => 'Building amazing apps', 'user_id' => 4],
        ]);
    }

    /** @test */
    public function it_applies_ilike_and_normalized_search()
    {
        $results = User::query()->pgSearch('Jane Doe', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane-Doe', $results->first()->name);
    }

    /** @test */
    public function it_searches_multiple_columns()
    {
        $results = User::query()->pgSearch('example', ['name', 'email'])->get();
        $this->assertCount(3, $results);
        $this->assertTrue($results->pluck('email')->contains('jane@example.com'));
        $this->assertTrue($results->pluck('email')->contains('john@example.com'));
        $this->assertTrue($results->pluck('email')->contains('oak@example.com'));
    }

    /** @test */
    public function it_normalizes_phone_numbers()
    {
        $users = User::all();
        $this->assertCount(4, $users);

        $jane = User::query()->where('name', 'Jane-Doe')->first();
        $this->assertEquals('123-456-7890', $jane->phone);

        $resultsExact = User::query()->pgSearch('123-456-7890', ['phone'])->get();
        $this->assertCount(1, $resultsExact);

        $resultsPartial = User::query()->pgSearch('456', ['phone'])->get();
        $this->assertCount(1, $resultsPartial);

        $resultsNormalized = User::query()->pgSearch('1234567890', ['phone'])->get();
        $this->assertCount(1, $resultsNormalized);
        $this->assertEquals('Jane-Doe', $resultsNormalized->first()->name);

        $resultsSpaced = User::query()->pgSearch('987 654 3210', ['phone'])->get();
        $this->assertCount(1, $resultsSpaced);
        $this->assertEquals('John Doe', $resultsSpaced->first()->name);
    }

    /** @test */
    public function it_searches_with_relationships()
    {
        $results = Post::query()->pgSearch('Jane', ['title', 'user.name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Tips', $results->first()->title);
    }

    /** @test */
    public function it_disables_normalization_when_requested()
    {
        $results = User::query()->pgSearch('Jane-Doe', ['name'], ['normalize' => false])->get();
        $this->assertCount(1, $results);

        $results = User::query()->pgSearch('Jane Doe', ['name'], ['normalize' => false])->get();
        $this->assertCount(0, $results);
    }

    /** @test */
    public function it_returns_all_results_for_empty_search()
    {
        $results = User::query()->pgSearch('', ['name'])->get();
        $this->assertCount(4, $results);

        $results = User::query()->pgSearch(null, ['name'])->get();
        $this->assertCount(4, $results);
    }

    /** @test */
    public function it_handles_case_insensitive_search()
    {
        // Test Oak-Li with various case combinations
        $results = User::query()->pgSearch('OAK-LI', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        $results = User::query()->pgSearch('oak-li', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        $results = User::query()->pgSearch('Oak-Li', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        $results = User::query()->pgSearch('oAk-Li', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        // Test partial matches with case insensitivity
        $results = User::query()->pgSearch('OAK', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        $results = User::query()->pgSearch('oak', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Oak-Li', $results->first()->name);

        // Test other users for completeness
        $results = User::query()->pgSearch('JANE', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane-Doe', $results->first()->name);

        $results = User::query()->pgSearch('john', ['name'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    /** @test */
    public function it_searches_partial_matches()
    {
        $results = User::query()->pgSearch('Doe', ['name'])->get();
        $this->assertCount(2, $results);
        $this->assertTrue($results->pluck('name')->contains('Jane-Doe'));
        $this->assertTrue($results->pluck('name')->contains('John Doe'));
    }

    /** @test */
    public function it_searches_across_text_content()
    {
        $results = Post::query()->pgSearch('framework', ['content'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Tips', $results->first()->title);

        $results = Post::query()->pgSearch('code', ['title', 'content'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('PHP Best Practices', $results->first()->title);
    }
}

class User extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'users';

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

class Post extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'posts';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
