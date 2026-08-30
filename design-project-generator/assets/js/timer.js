/**
 * Challenge timer.
 *
 * Counts down against wall-clock timestamps rather than accumulating ticks, so
 * a backgrounded tab that throttles its timers still shows the right number
 * when the visitor comes back to it.
 *
 * @package DesignProjectGenerator
 */

( function () {
	'use strict';

	var DPG = window.DPG;

	if ( ! DPG || ! DPG.use ) {
		return;
	}

	var t = DPG.t;

	/**
	 * Format seconds as mm:ss, or h:mm:ss once past an hour.
	 *
	 * @param {number} seconds Remaining seconds.
	 * @return {string}
	 */
	function format( seconds ) {
		seconds = Math.max( 0, Math.round( seconds ) );

		var hours = Math.floor( seconds / 3600 );
		var minutes = Math.floor( ( seconds % 3600 ) / 60 );
		var rest = seconds % 60;

		function pad( value ) {
			return value < 10 ? '0' + value : String( value );
		}

		if ( hours > 0 ) {
			return hours + ':' + pad( minutes ) + ':' + pad( rest );
		}

		return pad( minutes ) + ':' + pad( rest );
	}

	/**
	 * One timer, bound to one generator instance.
	 *
	 * @param {Object} instance Generator instance.
	 * @constructor
	 */
	function Timer( instance ) {
		this.instance = instance;
		this.el = instance.el;
		this.display = this.el.querySelector( '[data-dpg-timer-display]' );

		if ( ! this.display ) {
			return;
		}

		this.startButton = this.el.querySelector( '[data-dpg-timer-start]' );
		this.pauseButton = this.el.querySelector( '[data-dpg-timer-pause]' );
		this.stopButton = this.el.querySelector( '[data-dpg-timer-stop]' );
		this.custom = this.el.querySelector( '[data-dpg-timer-custom]' );

		this.duration = 1800;
		this.remaining = 1800;
		this.endsAt = 0;
		this.running = false;
		this.handle = null;

		this.bind();
		this.paint();
	}

	Timer.prototype.bind = function () {
		var self = this;

		Array.prototype.forEach.call(
			this.el.querySelectorAll( '[data-dpg-timer-preset]' ),
			function ( button ) {
				button.addEventListener( 'click', function () {
					self.set( parseInt( button.getAttribute( 'data-dpg-timer-preset' ), 10 ) );
					self.markPreset( button );
				} );
			}
		);

		if ( this.custom ) {
			this.custom.addEventListener( 'change', function () {
				var minutes = parseInt( self.custom.value, 10 );

				if ( minutes > 0 ) {
					self.set( Math.min( 600, minutes ) * 60 );
					self.markPreset( null );
				}
			} );
		}

		if ( this.startButton ) {
			this.startButton.addEventListener( 'click', function () {
				if ( self.running ) {
					return;
				}

				self.start();
			} );
		}

		if ( this.pauseButton ) {
			this.pauseButton.addEventListener( 'click', function () {
				self.pause();
			} );
		}

		if ( this.stopButton ) {
			this.stopButton.addEventListener( 'click', function () {
				self.stop();
			} );
		}

		// Coming back to a throttled tab should show the real remaining time.
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden && self.running ) {
				self.tick();
			}
		} );
	};

	Timer.prototype.markPreset = function ( active ) {
		Array.prototype.forEach.call(
			this.el.querySelectorAll( '[data-dpg-timer-preset]' ),
			function ( button ) {
				button.classList.toggle( 'is-active', button === active );
			}
		);
	};

	Timer.prototype.set = function ( seconds ) {
		seconds = Math.max( 10, Math.min( 86400, seconds || 0 ) );

		this.stop( true );
		this.duration = seconds;
		this.remaining = seconds;
		this.paint();
	};

	Timer.prototype.start = function () {
		if ( this.remaining <= 0 ) {
			this.remaining = this.duration;
		}

		this.running = true;
		this.endsAt = Date.now() + this.remaining * 1000;
		this.el.classList.add( 'dpg--timing' );

		if ( this.display ) {
			this.display.setAttribute( 'aria-live', 'off' );
		}

		this.updateButtons();
		this.instance.announce( t( 'timerStarted' ) );

		var self = this;

		this.handle = window.setInterval( function () {
			self.tick();
		}, 250 );

		this.tick();
	};

	Timer.prototype.tick = function () {
		this.remaining = Math.max( 0, ( this.endsAt - Date.now() ) / 1000 );
		this.paint();

		if ( this.remaining <= 0 ) {
			this.finish();
		}
	};

	Timer.prototype.pause = function () {
		if ( ! this.running ) {
			return;
		}

		this.running = false;
		this.remaining = Math.max( 0, ( this.endsAt - Date.now() ) / 1000 );

		window.clearInterval( this.handle );
		this.handle = null;

		this.el.classList.remove( 'dpg--timing' );
		this.updateButtons();
		this.paint();
		this.instance.announce( t( 'timerPaused' ) );
	};

	Timer.prototype.stop = function ( quiet ) {
		this.running = false;

		window.clearInterval( this.handle );
		this.handle = null;

		this.remaining = this.duration;
		this.el.classList.remove( 'dpg--timing', 'dpg--time-up' );
		this.updateButtons();
		this.paint();

		if ( ! quiet ) {
			this.instance.announce( t( 'timerStopped' ) );
		}
	};

	Timer.prototype.finish = function () {
		this.running = false;

		window.clearInterval( this.handle );
		this.handle = null;

		this.remaining = 0;
		this.el.classList.remove( 'dpg--timing' );
		this.el.classList.add( 'dpg--time-up' );
		this.updateButtons();
		this.paint();
		this.instance.announce( t( 'timeUp' ) );

		if ( this.display ) {
			this.display.setAttribute( 'aria-live', 'assertive' );
		}
	};

	Timer.prototype.updateButtons = function () {
		if ( this.startButton ) {
			this.startButton.disabled = this.running;
		}

		if ( this.pauseButton ) {
			this.pauseButton.disabled = ! this.running;
		}

		if ( this.stopButton ) {
			this.stopButton.disabled = ! this.running && this.remaining === this.duration;
		}
	};

	Timer.prototype.paint = function () {
		if ( this.display ) {
			this.display.textContent = format( this.remaining );
		}
	};

	DPG.use( {
		init: function ( instance ) {
			var timer = new Timer( instance );

			instance.timer = timer;
		},

		/**
		 * Preload the timer with the new brief's suggested duration.
		 *
		 * @param {Object} instance Generator instance.
		 */
		onProject: function ( instance ) {
			var timer = instance.timer;
			var challenge = instance.project && instance.project.challenge;

			if ( ! timer || ! timer.display || ! challenge ) {
				return;
			}

			if ( challenge.duration > 0 && ! timer.running ) {
				timer.set( challenge.duration );
			}
		}
	} );
}() );
