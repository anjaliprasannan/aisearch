# Milvus Vector Database Provider

## Using with DDEV.

1. Copy the `ddev-example.docker-compose.milvus.yaml` to your `.ddev` folder.
   1. Assuming your project uses the `web` docroot, you can use the below
      command:
      ```
      cp web/modules/vdb_providers/ai_vdb_provider_milvus/docs/docker-compose-examples/ddev-example.docker-compose.milvus.yaml .ddev/docker-compose.milvus.yaml
      ```
2. Run `ddev restart`
3. Access your Milvus UI at `https://{project}.ddev.site:8521`
3. Set up your Milvus Vector Database Plugin configuration to use:
   1. Host: `http://milvus`
   2. Port: `19530`
   3. If you are using authentication, create a key with the format "username:password" and add it.

## Connecting to a hosted Milvus instance.

You can use Zilliz Cloud for example at https://zilliz.com/cloud. To get
started:
1. Sign up for a free trial if you do not already have a plan
2. Use the credentials provided
