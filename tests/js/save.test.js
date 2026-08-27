/**
 * The block is dynamic: render.php produces the frontend markup, so save()
 * must contribute nothing to post_content. Returning markup here instead would
 * have the editor validate serialized HTML against the PHP render and mark
 * every existing block invalid.
 */
import { describe, it, expect } from 'vitest';
import save from '../../src/ceros/save';

describe( 'save', () => {
	it( 'saves no markup to post content', () => {
		expect( save() ).toBeNull();
	} );
} );
