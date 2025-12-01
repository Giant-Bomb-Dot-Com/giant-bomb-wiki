<template>
  <div class="filter-group">
    <label v-if="label" :for="id" class="filter-label">{{ label }}</label>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :placeholder="placeholder"
      class="filter-input"
    />
  </div>
</template>

<script>
/**
 * FilterInput Component
 * Generic reusable text input filter component
 */
const { defineComponent } = require("vue");

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
  },
  emits: ["update:modelValue"],
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
  border-color: #e63946;
}

.filter-input::placeholder {
  color: #666;
}
</style>
