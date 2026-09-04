module.exports = {
	plugins: [
		require( 'postcss-nested' ).default ?? require( 'postcss-nested' ),
		require( 'postcss-preset-env' ),
	],
};
