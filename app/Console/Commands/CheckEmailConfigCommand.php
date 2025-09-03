<?php

namespace App\Console\Commands;

use App\Services\EmailConfigService;
use Illuminate\Console\Command;

class CheckEmailConfigCommand extends Command
{
    protected $signature = 'email:check';
    protected $description = 'Check email configuration status and provide setup instructions';

    protected EmailConfigService $emailService;

    public function __construct(EmailConfigService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle()
    {
        $this->info('🔍 Checking Email Configuration...');
        $this->newLine();

        // Check current configuration
        $status = $this->emailService->getEmailStatus();
        
        $this->info('📧 Current Email Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Mailer', $status['mailer']],
                ['Host', $status['host'] ?? 'not set'],
                ['Port', $status['port'] ?? 'not set'],
                ['Username', $status['username'] ?? 'not set'],
                ['Password', $status['password']],
                ['Encryption', $status['encryption'] ?? 'not set'],
                ['Configured', $status['configured'] ? '✅ Yes' : '❌ No'],
            ]
        );

        if (!$status['configured']) {
            $this->newLine();
            $this->error('❌ Email is not properly configured!');
            $this->newLine();
            
            $this->info('📋 Setup Instructions:');
            $this->newLine();
            
            $instructions = $this->emailService->getConfigurationInstructions();
            
            foreach ($instructions as $provider => $config) {
                $this->info("🔧 {$config['name']}:");
                foreach ($config['instructions'] as $instruction) {
                    $this->line("   {$instruction}");
                }
                $this->newLine();
                
                $this->info("📝 .env Configuration for {$config['name']}:");
                foreach ($config['env_example'] as $envLine) {
                    $this->line("   {$envLine}");
                }
                $this->newLine();
            }
            
            $this->info('💡 Quick Setup (Gmail):');
            $this->line('1. Copy the .env.example file to .env');
            $this->line('2. Update the email settings in .env');
            $this->line('3. Run: php artisan email:test your-email@gmail.com');
            $this->newLine();
            
        } else {
            $this->newLine();
            $this->info('✅ Email is properly configured!');
            $this->newLine();
            
            $this->info('🧪 Test your email configuration:');
            $this->line('php artisan email:test your-email@example.com');
            $this->newLine();
        }

        return 0;
    }
}
