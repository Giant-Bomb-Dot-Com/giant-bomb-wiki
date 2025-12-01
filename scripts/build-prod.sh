#!/usr/bin/env bash
set -euo pipefail

# Build Dockerfile.prod with buildx for multi-arch or local amd64
# Examples:
#   Local (loads linux/amd64 into Docker): ./scripts/build-prod.sh --tag giant-bomb-wiki:local
#   Push multi-arch: ./scripts/build-prod.sh --tag ghcr.io/yourorg/giant-bomb-wiki:latest --push
#   Disable GCS FUSE in image: ./scripts/build-prod.sh --tag giant-bomb-wiki:local --fuse=false
#
# Flags:
#   --tag <name:tag>     Image tag (required)
#   --platform <list>    Platforms (default: linux/amd64 for local; linux/amd64,linux/arm64 when --push)
#   --push               Push image to registry (implies multi-arch)
#   --fuse=<true|false>  Build with GCSFUSE (default: true for push, false for local)
#

TAG=""
PLATFORMS=""
PUSH="false"
FUSE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tag)
      TAG="${2:-}"
      shift 2
      ;;
    --platform)
      PLATFORMS="${2:-}"
      shift 2
      ;;
    --push)
      PUSH="true"
      shift 1
      ;;
    --fuse=*)
      FUSE="${1#*=}"
      shift 1
      ;;
    -h|--help)
      sed -n '1,35p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 2
      ;;
  esac
done

if [[ -z "${TAG}" ]]; then
  echo "Error: --tag is required" >&2
  exit 2
fi

# Defaults
if [[ "${PUSH}" == "true" ]]; then
  PLATFORMS="${PLATFORMS:-linux/amd64,linux/arm64}"
  FUSE="${FUSE:-true}"
else
  PLATFORMS="${PLATFORMS:-linux/amd64}"
  FUSE="${FUSE:-false}"
fi

# Ensure a usable buildx builder
if ! docker buildx inspect >/dev/null 2>&1; then
  docker buildx create --use --name gbx >/dev/null
fi

echo "Building Dockerfile.prod"
echo "  tag:       ${TAG}"
echo "  platforms: ${PLATFORMS}"
echo "  push:      ${PUSH}"
echo "  gcsfuse:   ${FUSE}"
echo

if [[ "${PUSH}" == "true" ]]; then
  docker buildx build \
    --platform "${PLATFORMS}" \
    -f Dockerfile.prod \
    -t "${TAG}" \
    --build-arg "INSTALL_GCSFUSE=${FUSE}" \
    --push \
    .
else
  # --load only supports a single platform; default to linux/amd64 for local
  docker buildx build \
    --platform "${PLATFORMS}" \
    -f Dockerfile.prod \
    -t "${TAG}" \
    --build-arg "INSTALL_GCSFUSE=${FUSE}" \
    --load \
    .
fi

echo
echo "Build complete."
if [[ "${PUSH}" == "true" ]]; then
  echo "Pushed multi-arch image: ${TAG}"
else
  echo "Loaded local image: ${TAG}"
fi


