/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from './block.json';
import { ErrorBoundary } from './components/error-boundary';

/**
 * Wrapper component that adds error boundary protection
 */
const EditWithErrorBoundary = ( props ) => (
	<ErrorBoundary>
		<Edit { ...props } />
	</ErrorBoundary>
);

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( metadata.name, {
	icon: {
		src: (
			<svg
				width="28"
				height="30"
				viewBox="0 0 28 30"
				fill="none"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path
					d="M20.3601 19.6692C19.6401 20.6292 18.7301 21.4092 17.6801 21.9592C16.5701 22.5392 15.3501 22.8492 14.1001 22.8492C12.7701 22.8492 11.4701 22.4992 10.3001 21.8392C9.14008 21.1792 8.16008 20.2292 7.44008 19.0792C6.73008 17.9192 6.31008 16.5992 6.22008 15.2392C6.13008 13.8792 6.38008 12.5092 6.93008 11.2692C7.49008 10.0292 8.34008 8.94918 9.40008 8.13918C10.4701 7.31918 11.7201 6.78918 13.0301 6.60918C14.3501 6.41918 15.6901 6.57918 16.9301 7.05918C18.1201 7.52918 19.1701 8.27918 20.0201 9.24918H27.2701C26.1101 6.20918 24.0001 3.64918 21.2601 1.99918C18.3701 0.249182 14.9801 -0.380818 11.6901 0.219182C8.39008 0.809182 5.41008 2.59918 3.27008 5.25918C1.12008 7.92918 -0.0299159 11.2892 8.41258e-05 14.7592C0.0300841 18.2192 1.26008 21.5592 3.45008 24.1792C5.65008 26.7892 8.66008 28.5192 11.9701 29.0492C15.2701 29.5692 18.6601 28.8692 21.5101 27.0592C24.2101 25.3492 26.2801 22.7392 27.3801 19.6692H20.3601Z"
					fill="black"
				/>
			</svg>
		),
	},

	/**
	 * @see ./edit.js
	 */
	edit: EditWithErrorBoundary,

	/**
	 * @see ./save.js
	 */
	save,
} );
