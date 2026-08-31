<?php

namespace App\Console\Commands;

use App\Services\DeviceDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\LaravelClient\Facades\MQTT;

class ListenMqttCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to MQTT topics for Auto-Discovery, device states, and LWT availability';

    /**
     * Execute the console command.
     */
    public function handle(DeviceDiscoveryService $discoveryService): int
    {
        $this->info("==========================================");
        $this->info("  Starting Laravel MQTT Background Listener ");
        $this->info("==========================================");

        try {
            $mqtt = MQTT::connection();

            // 1. Subscribe to Auto-Discovery topic
            $this->info("Subscribing to: home/discovery/+/config");
            $mqtt->subscribe('home/discovery/+/config', function (string $topic, string $message) use ($discoveryService) {
                $this->line("[DISCOVERY] Received payload on: {$topic}");
                $discoveryService->handleDiscovery($topic, $message);
            }, 0);

            // 2. Subscribe to State updates (e.g. from physical button or relay state sync)
            $this->info("Subscribing to: home/+/relay1/state");
            $mqtt->subscribe('home/+/relay1/state', function (string $topic, string $message) use ($discoveryService) {
                $this->line("[STATE] Topic: {$topic} -> State: {$message}");
                $discoveryService->handleState($topic, $message);
            }, 0);

            // 3. Subscribe to Availability status (online / offline from LWT)
            $this->info("Subscribing to: home/+/status");
            $mqtt->subscribe('home/+/status', function (string $topic, string $message) use ($discoveryService) {
                $this->line("[AVAILABILITY] Topic: {$topic} -> Status: {$message}");
                $discoveryService->handleAvailability($topic, $message);
            }, 0);

            $this->info("Listening for MQTT messages... (Press CTRL+C to stop)");

            // Loop indefinitely
            $mqtt->loop(true);

        } catch (\Throwable $e) {
            $this->error("MQTT Listener Error: " . $e->getMessage());
            Log::error("MQTT Listener crashed: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
