import { addRecord, addRecords } from '../helpers'
import * as types from '../mutation-types'

export default {
  state: {
    currentID: null,
    loading: false,
    episode: null,
    url: null,
  },
  mutations: {
    [types.CLEAR_ALL] (state) {
      state.all = []
    },
    [types.LOADING_EPISODES] (state) {
      state.loading = true
    },
    [types.LOADED_EPISODES] (state) {
      state.loading = false
    },
    [types.SELECT_EPISODE] (state, id) {
      state.currentID = id
    },
    [types.ADD_EPISODE] (state, episode) {
      state.episode = episode
      state.url = episode.attributes.folder_path.split("/public/", 2)[1]     
      //addRecord(state.all, episode, 'episodes', updateRelationships)
    }
  },
  actions: {

    getEpisode (context, payload) {
      context.commit(types.SELECT_EPISODE, payload.id)

      // GET /api/episode/{id}
      axios.get(payload.url)
        .then(function (response) {
          // success callback
          let episode = response.data
          context.commit(types.ADD_EPISODE, episode.data)
          context.commit(types.LOADED_ROUTE)
        })
        .catch(function (error) {
          // error callback
          context.commit(types.ADD_TOAST, {
            error: 'Connection Error'
          })
        })
    }
  }
}
