/**
 * Error Boundary Component
 *
 * Catches JavaScript errors in child components and displays
 * a user-friendly fallback UI instead of crashing the editor.
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			hasError: false,
			error: null,
		};
	}

	static getDerivedStateFromError( error ) {
		// Update state so the next render shows the fallback UI
		return {
			hasError: true,
			error,
		};
	}

	componentDidCatch( error, errorInfo ) {
		// Log error for debugging (only in development)
		if ( typeof console !== 'undefined' && console.error ) {
			console.error( 'Ceros Block Error:', error, errorInfo );
		}
	}

	handleRetry = () => {
		this.setState( {
			hasError: false,
			error: null,
		} );
	};

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="ceros-block__error-boundary">
					<div className="ceros-block__error-boundary-content">
						<svg
							className="ceros-block__error-boundary-icon"
							xmlns="http://www.w3.org/2000/svg"
							width="48"
							height="48"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
						>
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="8" x2="12" y2="12"></line>
							<line x1="12" y1="16" x2="12.01" y2="16"></line>
						</svg>
						<h3>{ __( 'Something went wrong', 'ceros' ) }</h3>
						<p>
							{ __(
								'The Ceros block encountered an error. This might be a temporary issue.',
								'ceros'
							) }
						</p>
						<button
							className="ceros-block__error-boundary-button"
							onClick={ this.handleRetry }
						>
							{ __( 'Try Again', 'ceros' ) }
						</button>
					</div>
				</div>
			);
		}

		return this.props.children;
	}
}
