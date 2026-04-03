[Back to documentation](README.md)

# Troubleshooting

Common issues and solutions when using Laravel Langfuse.

## Langfuse v3 Compatibility

### Issue: "Invalid request data" errors with Langfuse v3

**Symptoms:**
- HTTP 400 errors in Laravel logs: `"Invalid request data"`
- Traces appear in ClickHouse but not in the Langfuse UI
- UI shows "undefined" for trace outputs
- Error in Langfuse logs: `ZodError: [Invalid request data]`

**Root Cause:**
Langfuse v3 introduced breaking changes:
- Asynchronous ingestion architecture (returns 207 status)
- Stricter SDK version requirements (>= 2.0.0)
- Stricter type validation for input/output fields

**Solution:**
Update to the latest version of this package, which includes:
- SDK version updated to 2.0.0 for v3 compatibility
- Enhanced error logging to diagnose validation issues
- Proper handling of 207 Multi-Status responses

```bash
composer update axyr/laravel-langfuse
```

### Understanding Langfuse v3 Architecture

Langfuse v3 uses an asynchronous processing pipeline:

1. **Ingestion** → Events are immediately written to S3/blob storage
2. **Queueing** → References are stored in Redis for processing
3. **Processing** → Workers ingest data into ClickHouse (analytics)
4. **Synchronization** → Data appears in PostgreSQL (UI)

**Important:** Traces may take a few seconds to appear in the UI. This is normal behavior in v3.

### Checking for Errors

If traces aren't appearing, check your Laravel logs:

```bash
tail -f storage/logs/laravel.log | grep "Langfuse"
```

Look for warnings like:
```
[2026-04-03] local.WARNING: Langfuse ingestion event error
{"id":"...", "status":400, "message":"Invalid request data", "error":"..."}
```

The `error` field (new in this release) provides details about what validation failed.

### Common v3 Issues

**1. SDK Version Rejection**

If you see `SDK version not supported`, ensure you're using the latest package version that reports SDK version 2.0.0.

**2. Input/Output Type Errors**

Error: `"Expected object, received string"`

**Solution:** Ensure input/output fields are properly serializable. Avoid passing complex objects that can't be JSON-encoded.

**3. Metadata Type Errors**

Error: `"Metadata must be an object"`

**Solution:** Metadata must be a key-value object, not a string or array. The package handles this automatically.

**4. Delayed Trace Visibility**

**Issue:** Traces don't appear immediately in the UI

**Solution:** This is expected in v3. Wait 5-10 seconds and refresh. For self-hosted deployments, ensure:
- S3/MinIO is properly configured
- Worker containers are running
- Redis is accessible

### Self-Hosted v3 Troubleshooting

For self-hosted Langfuse v3, verify:

```bash
# Check worker is running
docker ps | grep langfuse-worker

# Check Redis connectivity
docker logs langfuse-web | grep -i redis

# Check S3/blob storage
docker logs langfuse-web | grep -i "s3\|blob\|storage"
```

Common self-hosted issues:
- **S3 credentials missing** - Set `S3_ACCESS_KEY_ID` and `S3_SECRET_ACCESS_KEY`
- **Worker not running** - Ensure `langfuse-worker` container is started
- **Network isolation** - Verify web and worker containers can communicate

## General Issues

### Events not appearing

**Check if tracing is enabled:**
```bash
php artisan tinker
>>> config('langfuse.enabled')
```

**Check credentials:**
```bash
>>> config('langfuse.public_key')
>>> config('langfuse.secret_key')
```

**Force a manual flush:**
```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

// After creating traces
Langfuse::flush();
```

### High memory usage

If you're seeing memory issues, reduce the flush threshold:

```env
LANGFUSE_FLUSH_AT=5  # Default is 10
```

Or enable queue-based batching:

```env
LANGFUSE_QUEUE=langfuse
```

### Auto-instrumentation not working

**Laravel AI not tracing:**
```env
LANGFUSE_LARAVEL_AI_ENABLED=true  # Must be explicitly enabled
```

**Prism not tracing:**
```env
LANGFUSE_PRISM_ENABLED=true  # Or enable Laravel AI (auto-enables Prism)
```

**Neuron AI not tracing:**
```env
LANGFUSE_NEURON_AI_ENABLED=true
```

### Testing issues

If tests are failing due to Langfuse API calls, use the fake:

```php
use Axyr\Langfuse\LangfuseFacade as Langfuse;

Langfuse::fake();

// Your test code...

Langfuse::assertNothingSent();
```

## Getting Help

If you're still experiencing issues:

1. **Enable debug logging** - Set `LOG_LEVEL=debug` in `.env`
2. **Check compatibility** - Verify PHP 8.2+, Laravel 12/13, Langfuse v2/v3
3. **Review logs** - Check both Laravel and Langfuse logs
4. **Create an issue** - [GitHub Issues](https://github.com/axyr/laravel-langfuse/issues) with:
   - Package version (`composer show axyr/laravel-langfuse`)
   - Langfuse version (cloud or self-hosted version)
   - Error messages from logs
   - Minimal reproduction steps

---

Previous: [Neuron AI Integration](integrations/neuron-ai.md)
