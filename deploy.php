<?php

namespace Deployer;

// Include the Laravel & rsync recipes
require 'recipe/laravel.php';
require 'recipe/rsync.php';
require 'recipe/slack.php';


set('slack_webhook', 'https://hooks.slack.com/services/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');
set('slack_text', '_{{user}}_ deploying `{{branch}}` to *{{target}}*');
set('slack_success_text', 'Deploy to *{{target}}* successful');
set('slack_failure_text', 'Deploy to *{{target}}* failed');

set('application', 'My App');
set('ssh_multiplexing', true); // Speeds up deployments

set('rsync_src', function () {
    return __DIR__; // If your project isn't in the root, you'll need to change this.
});

// Configuring the rsync exclusions.
// You'll want to exclude anything that you don't want on the production server.
add('rsync', [
    'exclude' => [
        '.git',
        '/.env',
        '/storage/',
        '/vendor/',
        '/node_modules/',
        '.github',
        'deploy.php',
    ],
]);


// Set up a deployer task to copy secrets to the server.
// Since our secrets are stored in Gitlab, we can access them as env vars.
task('deploy:secrets', function () {
    file_put_contents(__DIR__ . '/.env', getenv('DOT_ENV'));
    upload('.env', get('deploy_path') . '/shared');
});

before('deploy', 'slack:notify');

// Staging Server
host('laravel-deployer.notus.dev') // Name of the server
->hostname('5.189.130.105') // Hostname or IP address
->stage('staging') // Deployment stage (production, staging, etc)
->user('root') // SSH user
->set('deploy_path', '/var/www/vhosts/notus.dev/laravel-deployer-test.notus.dev'); // Deploy path

task('deploy:hello', function () {
    run('ls -la');
});

after('deploy:failed', 'deploy:unlock'); // Unlock after failed deploy
after('success', 'slack:notify:success');

desc('Deploy the application');
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'rsync', // Deploy code & built assets
    'deploy:secrets', // Deploy secrets
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'artisan:storage:link', // |
    'artisan:view:cache',   // |
    'artisan:config:cache', // | Laravel Specific steps
    //'artisan:optimize',     // |
    'artisan:migrate',      // |
    'artisan:queue:restart',// |
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
]);

