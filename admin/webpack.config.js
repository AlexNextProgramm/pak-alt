const { webpack } = require("./vendor/pet/framework/Frontend/webpack");
const CopyWebpackPlugin = require("copy-webpack-plugin");
const path = require("path");

webpack.watchOptions = {
    ignored: [
        '**/node_modules/**',
        '**/vendor/**',
        '**/view/assets/**',
        '**/public/view/page/**/head.php',
        '**/.git/**',
    ],
    aggregateTimeout: 300,
};

webpack.plugins.push(
    new CopyWebpackPlugin({
        patterns: [
            {
                from: path.resolve(__dirname, "node_modules/ckeditor4"),
                to: "view/assets/ckeditor",
                globOptions: {
                    ignore: ["**/samples/**"],
                },
            },
        ],
    })
);

module.exports = [webpack];
