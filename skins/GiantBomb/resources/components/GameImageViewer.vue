<template>
  <div class="game-image-viewer">
    <div class="game-image-container" @click="toggleFullscreen">
      <img :src="imageUrl" :alt="altText" class="game-image" />
      <div class="game-image-overlay">
        <span class="game-image-icon">🔍</span>
        <span class="game-image-hint">Click to view full size</span>
      </div>
    </div>

    <transition name="fade">
      <div
        v-if="isFullscreen"
        class="game-image-fullscreen"
        @click="closeFullscreen"
      >
        <div class="game-image-fullscreen-content">
          <button class="game-image-close" @click.stop="closeFullscreen">
            ✕
          </button>
          <img
            :src="imageUrl"
            :alt="altText"
            class="game-image-full"
            @click.stop
          />
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
const { ref, toRefs, onMounted, onUnmounted } = require("vue");

module.exports = exports = {
  name: "GameImageViewer",
  props: {
    imageUrl: {
      type: String,
      required: true,
    },
    altText: {
      type: String,
      default: "Game image",
    },
  },
  setup(props) {
    const { imageUrl, altText } = toRefs(props);
    const isFullscreen = ref(false);

    const toggleFullscreen = () => {
      isFullscreen.value = !isFullscreen.value;
      if (isFullscreen.value) {
        document.body.style.overflow = "hidden";
      } else {
        document.body.style.overflow = "";
      }
    };

    const closeFullscreen = () => {
      isFullscreen.value = false;
      document.body.style.overflow = "";
    };

    const handleEscape = (e) => {
      if (e.key === "Escape" && isFullscreen.value) {
        closeFullscreen();
      }
    };

    onMounted(() => {
      document.addEventListener("keydown", handleEscape);
    });

    onUnmounted(() => {
      document.removeEventListener("keydown", handleEscape);
      document.body.style.overflow = "";
    });

    return {
      imageUrl,
      altText,
      isFullscreen,
      toggleFullscreen,
      closeFullscreen,
    };
  },
};
</script>

<style>
.game-image-viewer {
  position: relative;
}

.game-image-container {
  position: relative;
  cursor: pointer;
  overflow: hidden;
  border-radius: 8px;
  transition: transform 0.2s ease;
}

.game-image-container:hover {
  transform: scale(1.02);
}

.game-image-container:hover .game-image-overlay {
  opacity: 1;
}

.game-image {
  width: 100%;
  height: auto;
  display: block;
}

.game-image-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  color: white;
  gap: 0.5rem;
}

.game-image-icon {
  font-size: 3rem;
}

.game-image-hint {
  font-size: 0.9rem;
  font-weight: 500;
}

.game-image-fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.95);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.game-image-fullscreen-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  cursor: default;
}

.game-image-full {
  max-width: 100%;
  max-height: 90vh;
  object-fit: contain;
}

.game-image-close {
  position: absolute;
  top: -40px;
  right: 0;
  background: transparent;
  border: none;
  color: white;
  font-size: 2rem;
  cursor: pointer;
  padding: 0.5rem 1rem;
  transition: transform 0.2s ease;
}

.game-image-close:hover {
  transform: scale(1.2);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
