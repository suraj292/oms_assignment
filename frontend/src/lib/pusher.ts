import Pusher from 'pusher-js'

// Initialize Pusher
const pusher = new Pusher('a1b939969b21e1dbc826', {
    cluster: 'ap2',
})

export default pusher
