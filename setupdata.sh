while [ true ]; do
    case "$1" in
        --smw)
            SMW=1
            shift
            ;;
        *)
            break
            ;;
    esac
done

WIKI_CONTAINER=$(docker compose -f docker-compose.snapshot.yml ps -q wiki)

if [ "$SMW" = "1" ]; then
    echo "Rebuilding SMW data..."
    echo "⏳ This can hours to run as well"
    docker exec $WIKI_CONTAINER /bin/bash smw-rebuild.sh
    echo "✓ SMW Rebuild Complete"
else
    echo "Importing wiki data..."
    echo "⏳ This can take hours to run. It will split the load across all cores as evenly as possible to speed things up"
    echo ""

    docker cp installwikidata.sh $WIKI_CONTAINER:/installwikidata.sh
    docker cp smw-rebuild.sh $WIKI_CONTAINER:/smw-rebuild.sh
    docker exec $WIKI_CONTAINER chmod 755 /installwikidata.sh
    docker exec $WIKI_CONTAINER chmod 755 /smw-rebuild.sh
    docker exec $WIKI_CONTAINER /bin/bash /installwikidata.sh
    docker exec $WIKI_CONTAINER rm /data/fulldatadump.xml
    echo "✓ Full data backup installed"
fi
