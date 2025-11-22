<?php
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

Route::get('/dangerous-db-reset', function () {

    // 1. Security Check
    if (request()->query('key') !== 'resetdb') {
        abort(403, 'Unauthorized action.');
    }

    // 2. Disable Time Limits & Buffering
    set_time_limit(0); 
    
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    // 3. Prepare Layout
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Database Reset Logs</title>
        <style>
            body { background-color: #1e1e1e; color: #00ff00; font-family: "Courier New", monospace; padding: 20px; font-size: 14px; }
            .log { margin: 2px 0; white-space: pre-wrap; } /* pre-wrap keeps formatting */
            .error { color: #ff4444; }
            .command-title { color: #00ffff; font-weight: bold; margin-top: 20px; border-top: 1px dashed #555; padding-top: 10px; }
        </style>
    </head>
    <body>
    <h3>🚀 STARTING SYSTEM RESET SEQUENCE...</h3>
    <pre id="logs">';
    
    flush();

    // 4. Define All Commands to Run
    $commandsToRun = [
        // Command 1: The Big Reset
        [PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--force'],
        
        // Command 2: Clear Config
        [PHP_BINARY, 'artisan', 'config:clear'],
        
        // Command 3: Clear Cache
        [PHP_BINARY, 'artisan', 'cache:clear'],
        
        // Command 4: Clear Routes
        [PHP_BINARY, 'artisan', 'route:clear'],
        
        // Optional: Clear View/Compiled classes
        [PHP_BINARY, 'artisan', 'view:clear'],
    ];

    // 5. Loop and Execute Each Command
    foreach ($commandsToRun as $cmd) {
        
        // Pretty print the command name
        $commandName = implode(' ', array_slice($cmd, 1)); // e.g., "artisan config:clear"
        echo "<div class='command-title'>$ > executing: $commandName ...</div>";
        flush();

        $process = new Process($cmd);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) {
            $output = nl2br(htmlspecialchars($buffer));
            
            if (Process::ERR === $type) {
                echo "<div class='log error'>$output</div>";
            } else {
                echo "<div class='log'>$output</div>";
            }

            echo '<script>window.scrollTo(0, document.body.scrollHeight);</script>';
            
            if(ob_get_length() > 0) ob_flush();
            flush();
        });
    }

    // 6. Finish
    echo '</pre>';
    echo '<h3>✅ ALL PROCESSES COMPLETED SUCCESSFULLY!</h3>';
    echo '<br><a href="/" style="color: white; font-size: 1.2em; text-decoration: underline;">[ Go Back to Home ]</a>';
    echo '</body></html>';
});