<template>
  <div>
    Example of a Vue Single File Component with the title {{ title }}.
    <another-vue-component :some-number="Math.random()"></another-vue-component>
  </div>
</template>

<script>

const {toRefs} = require('vue');
const AnotherVueComponent = require('./AnotherVueComponent.vue')

module.exports = {
  name: 'App',
  components: {AnotherVueComponent},
  props: {
    title: String
  },
  setup(props) {
    const {title} = toRefs(props)

    return {
      title
    }
  }
}

</script>
