FROM amazon/dynamodb-local
HEALTHCHECK --interval=5s --timeout=3s --start-period=0s --retries=5 \
    CMD curl --silent --output /dev/null http://localhost:8000/shell/
CMD ["-jar", "/home/dynamodblocal/DynamoDBLocal.jar", "-inMemory"]
