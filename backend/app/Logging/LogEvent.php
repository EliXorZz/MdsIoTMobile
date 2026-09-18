<?php

namespace App\Logging;

enum LogEvent: string
{
    // MQTT
    case MqttConnected = 'mqtt.connected';
    case MqttConnectionFailed = 'mqtt.connection_failed';
    case MqttConnectionLost = 'mqtt.connection_lost';
    case MqttSubscribed = 'mqtt.subscribed';
    case MqttSubscriptionFailed = 'mqtt.subscription_failed';
    case MqttReconnectAttempt = 'mqtt.reconnect_attempt';

    // Telemetry ingestion
    case TelemetryReceived = 'telemetry.received';
    case TelemetryAnomaly = 'telemetry.anomaly';
    case RabbitmqPublished = 'rabbitmq.published';

    // Batch consumer (generic)
    case BatchListening = 'batch_consumer.listening';
    case BatchThroughput = 'batch_consumer.throughput';
    case BatchNacked = 'batch_consumer.batch_nacked';

    // Telemetry consumer
    case TelemetryMessageDropped = 'telemetry_consumer.message_dropped';
    case TelemetryInvalidPayload = 'telemetry_consumer.invalid_payload';
    case TelemetryConsumerLag = 'telemetry_consumer.lag';

    // Device state / availability
    case StateReceived = 'state.received';
    case StateRejected = 'state.rejected';
    case AvailabilityReceived = 'availability.received';
    case AvailabilityRejected = 'availability.rejected';

    // Command results
    case CommandResultReceived = 'command_result.received';
    case CommandResultRejected = 'command_result.rejected';
}
