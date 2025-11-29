import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === 'production';

const nextConfig: NextConfig = {
  // Only use static export in production, allow API routes in development
  output: isProd ? 'export' : undefined,
  basePath: isProd ? '/WG/analysis/OCC' : '',
  assetPrefix: isProd ? '/WG/analysis/OCC' : '',
  images: {
    unoptimized: true,
  },
};

export default nextConfig;
