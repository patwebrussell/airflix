<template>
    <div class="hello">
      <video controls id="myvideo" :src="url"></video>
   </div>
</template>

<script>

import { mapActions } from 'vuex'

export default {

   name: 'hello',
   created: function () {
      this.loadingRoute()
      this.fetchData()
   },
   watch: {
    '$route': function () {
      this.loadingRoute()
      this.fetchData()
    }
  },
    methods: {
    ...mapActions([
      'getEpisode',
      'loadingRoute',
      'player'
    ]),

    fetchData: function () {
      let payload = {
        id: this.$route.params.id,
        url: '/api/episodes/' + this.$route.params.id
      }

      this.getEpisode(payload)
    }
  },
  computed: {
    loadingRouteData: function () {
      return this.$store.state.interfaces.loadingRouteData
    },
   episode: function () {
      let state = this.$store.state

      return state.episodes.all.find(
        m => m.id == state.episodes.currentID
      )
    },
  }
}
</script>