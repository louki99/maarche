export const environment = {
  production: true,
  VERSION: require('../../../package.json').version,
  BASE_VERSION: require('../../../package.json').version.split('.')[0],
  AUTHOR: require('../../../package.json').author
};
