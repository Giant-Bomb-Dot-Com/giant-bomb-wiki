#!/bin/bash

set -e

BASE_COMPOSE_FILE="docker-compose.yml" 
DEV_COMPOSE_FILE="docker-compose.dev.yml"
PROD_COMPOSE_FILE="docker-compose.prod.yml"

show_usage() {
  echo "Usage: $0 {dev|prod} [command]"
  echo "  Starts or manages the Docker Compose stack for the specified environment."
  echo ""
  echo "  Examples:"
  echo "    $0 dev up -d      # Start dev environment in detached mode"
  echo "    $0 prod up -d     # Start prod environment in detached mode"
  echo "    $0 dev down -v    # Stop and remove dev containers and volumes"
  echo "    $0 prod ps        # List prod services"
  echo "    $0 dev build      # Build dev images"
  exit 1
}

if [ -z "$1" ]; then 
  echo "Error: Please specify 'dev' or 'prod' as the first argument."
  show_usage
fi

ENV="$1"
shift

COMPOSE_FILES=""

case "$ENV" in
  dev)
    COMPOSE_FILES="-f ${BASE_COMPOSE_FILE} -f ${DEV_COMPOSE_FILE}"
    echo "Development environment..."
    ;;
  prod)
    COMPOSE_FILES="-f ${BASE_COMPOSE_FILE} -f ${PROD_COMPOSE_FILE}"
    echo "Production environment..."
    ;;
  *)
    echo "Error: Invalid environment specified: $ENV"
    show_usage
    ;;
esac

docker compose ${COMPOSE_FILES} "$@"

echo "Docker Compose command finished for environment: $ENV"