<template>
  <div>
    This is another Vue Component {{ someNumber }}
  </div>
</template>

<script>

const {toRefs} = require('vue');

module.exports = {
  name: 'App',
  props: {
    someNumber: Number
  },
  setup(props) {
    const { someNumber } = toRefs(props)

    return {
      someNumber
    }
  }
}

</script>
