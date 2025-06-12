<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckOAuthConfig extends Command
{
    protected $signature = 'oauth:check {provider?}';

    protected $description = 'Verificar la configuración OAuth para los proveedores sociales';

    public function handle()
    {
        $provider = $this->argument('provider');
        
        if ($provider) {
            $this->checkProvider($provider);
        } else {
            $this->info('🔍 Verificando configuración OAuth...');
            $this->newLine();
            
            $this->checkGeneralConfig();
            $this->newLine();
            
            $providers = ['google', 'facebook'];
            foreach ($providers as $provider) {
                $this->checkProvider($provider);
                $this->newLine();
            }
        }
    }
    
    private function checkGeneralConfig()
    {
        $this->info('📋 Configuración General:');
        
        $appUrl = env('APP_URL');
        $nextjsUrl = env('NEXTJS_URL');
        
        $this->line("  APP_URL: " . ($appUrl ? "✅ {$appUrl}" : "❌ No configurado"));
        $this->line("  NEXTJS_URL: " . ($nextjsUrl ? "✅ {$nextjsUrl}" : "❌ No configurado"));
    }
    
    private function checkProvider($provider)
    {
        $this->info("🔑 Configuración {$provider}:");
        
        $config = config("services.{$provider}");
        
        if (!$config) {
            $this->error("  ❌ Proveedor {$provider} no configurado");
            return;
        }
        
        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;
        $redirect = $config['redirect'] ?? null;
        
        $this->line("  Client ID: " . ($clientId ? "✅ Configurado" : "❌ No configurado"));
        $this->line("  Client Secret: " . ($clientSecret ? "✅ Configurado" : "❌ No configurado"));
        $this->line("  Redirect URI: " . ($redirect ? "✅ {$redirect}" : "❌ No configurado"));
        
        if ($clientId && $clientSecret && $redirect) {
            $this->info("  ✅ {$provider} completamente configurado");
        } else {
            $this->warn("  ⚠️  {$provider} requiere configuración adicional");
            $this->line("     Añade estas variables a tu archivo .env:");
            $this->line("     " . strtoupper($provider) . "_CLIENT_ID=tu_client_id");
            $this->line("     " . strtoupper($provider) . "_CLIENT_SECRET=tu_client_secret");
        }
    }
}
