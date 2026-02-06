<template>
  <div class="filter-group">
    <label v-if="label" :for="id" class="filter-label">{{ label }}</label>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      @input="handleInput"
      :placeholder="placeholder"
      class="filter-input"
    />
  </div>
</template>

<script>
/**
 * FilterInput Component
 * Generic reusable text input filter component with optional debouncing
 */
const { defineComponent, onUnmounted } = require("vue");

const component = defineComponent({
  name: "FilterInput",
  props: {
    id: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      default: "",
    },
    modelValue: {
      type: [String, Number],
      default: "",
    },
    placeholder: {
      type: String,
      default: "",
    },
    type: {
      type: String,
      default: "text",
    },
    debounce: {
      type: Number,
      default: 0,
    },
  },
  emits: ["update:modelValue"],
  setup(props, { emit }) {
    let debounceTimeout = null;

    const handleInput = (event) => {
      const value = event.target.value;

      if (props.debounce > 0) {
        // Clear existing timeout
        if (debounceTimeout) {
          clearTimeout(debounceTimeout);
        }

        // Wait for debounce delay before emitting
        debounceTimeout = setTimeout(() => {
          emit("update:modelValue", value);
        }, props.debounce);
      } else {
        // No debouncing, emit immediately
        emit("update:modelValue", value);
      }
    };

    onUnmounted(() => {
      // Clear any pending timeout
      if (debounceTimeout) {
        clearTimeout(debounceTimeout);
      }
    });

    return {
      handleInput,
    };
  },
});

module.exports = exports = component;
exports.default = component;
</script>

<style>
.filter-input {
  width: 100%;
  padding: 10px;
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}

.filter-input:hover {
  border-color: #666;
}

.filter-input:focus {
  outline: none;
  border-color: #b05f1c;
}

.filter-input::placeholder {
  color: #666;
}
</style>
